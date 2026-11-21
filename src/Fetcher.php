<?php

namespace IProDev\Sitemap;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use IProDev\Sitemap\Http\LimitedStream;
use IProDev\Sitemap\Http\PrivateNetworkGuard;
use IProDev\Sitemap\Http\ResponseTooLargeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Fetcher
{
    /** Default maximum size of a (decoded) response body: 10 MB */
    public const DEFAULT_MAX_RESPONSE_SIZE = 10485760;

    /**
     * Options handled by this class; everything else is passed to the Guzzle client
     */
    private const OWN_OPTIONS = [
        'concurrency', 'max_response_size', 'block_private_ips', 'resolver', 'proxies',
        'enable_javascript', 'chrome_path', 'chrome_port', 'wait_for_ajax', 'ajax_timeout',
    ];

    private Client $client;
    private int $concurrency;
    private LoggerInterface $logger;
    private int $maxResponseSize;

    /** @var string[] proxies used in rotation, one per request */
    private array $proxies = [];
    private int $proxyIndex = 0;

    /**
     * @param array $options Guzzle client options plus:
     *   - concurrency (int, 1-100, default 10)
     *   - max_response_size (int bytes, default 10 MB, 0 = unlimited): larger bodies are rejected
     *   - block_private_ips (bool, default false): refuse requests (and redirects) to private,
     *     loopback, link-local and other non-public addresses
     *   - proxies (string[]): rotate through these proxy URLs, one per request
     */
    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();

        // Validate concurrency
        $concurrency = $options['concurrency'] ?? 10;
        if ($concurrency < 1 || $concurrency > 100) {
            throw new \InvalidArgumentException('Concurrency must be between 1 and 100');
        }
        $this->concurrency = $concurrency;

        $this->maxResponseSize = max(0, (int)($options['max_response_size'] ?? self::DEFAULT_MAX_RESPONSE_SIZE));
        $this->proxies = array_values(array_filter((array)($options['proxies'] ?? []), 'is_string'));

        $defaults = [
            'headers' => [
                'User-Agent' => 'php-sitemap-generator/' . Version::NUMBER
                    . ' (+https://github.com/iprodev/PHP-XML-Sitemap-Generator)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1'
            ],
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 10,
                'strict' => false,
                'referer' => true,
                'track_redirects' => true
            ],
            'timeout' => 15,
            'connect_timeout' => 10,
            'verify' => true
        ];

        $clientOptions = array_merge($defaults, array_diff_key($options, array_flip(self::OWN_OPTIONS)));

        if (!empty($options['block_private_ips'])) {
            $handler = $clientOptions['handler'] ?? null;
            $stack = $handler instanceof HandlerStack ? $handler : HandlerStack::create($handler);
            // push() adds the middleware inside the redirect middleware, so every hop is checked
            $stack->push(PrivateNetworkGuard::middleware($options['resolver'] ?? null), 'block_private_ips');
            $clientOptions['handler'] = $stack;
        }

        $this->client = new Client($clientOptions);

        $this->logger->info("Fetcher initialized", [
            'concurrency' => $this->concurrency,
            'timeout' => $clientOptions['timeout'],
            'max_response_size' => $this->maxResponseSize,
            'block_private_ips' => !empty($options['block_private_ips']),
            'rotating_proxies' => count($this->proxies),
        ]);
    }

    /**
     * Fetch multiple URLs concurrently (GET).
     *
     * Callbacks receive the index of the URL in $urls (invalid URLs are skipped
     * without shifting the indexes of the others).
     *
     * @param string[] $urls
     * @param callable $onFulfilled function(ResponseInterface $response, int|string $index, ?float $transferTime)
     * @param callable|null $onRejected function($reason, int|string $index)
     */
    public function fetchMany(array $urls, callable $onFulfilled, ?callable $onRejected = null): void
    {
        if (empty($urls)) {
            $this->logger->debug("No URLs to fetch");
            return;
        }

        /** @var array<int|string, float> $transferTimes */
        $transferTimes = [];

        $requests = function () use ($urls, &$transferTimes) {
            foreach ($urls as $idx => $url) {
                if (!$this->isValidUrl($url)) {
                    $this->logger->warning("Invalid URL skipped: {$url}");
                    continue;
                }

                yield $idx => function () use ($url, $idx, &$transferTimes): PromiseInterface {
                    $onStats = function (TransferStats $stats) use ($idx, &$transferTimes): void {
                        // Sum over redirect hops
                        $transferTimes[$idx] = ($transferTimes[$idx] ?? 0.0) + (float)$stats->getTransferTime();
                    };
                    return $this->sendAsync($url, ['on_stats' => $onStats]);
                };
            }
        };

        $defaultRejected = function ($reason, $idx) use ($urls) {
            $url = $urls[$idx] ?? 'unknown';
            $message = $reason instanceof \Throwable ? $reason->getMessage() : (string)$reason;
            $this->logger->warning("Request failed for {$url}", ['reason' => $message]);
        };

        try {
            $pool = new Pool($this->client, $requests(), [
                'concurrency' => $this->concurrency,
                'fulfilled'   => function ($response, $idx) use ($onFulfilled, $urls, &$transferTimes) {
                    $this->logger->debug("Successfully fetched {$urls[$idx]}", [
                        'status' => $response->getStatusCode()
                    ]);
                    $onFulfilled($response, $idx, $transferTimes[$idx] ?? null);
                },
                'rejected'    => $onRejected ?? $defaultRejected
            ]);

            $pool->promise()->wait();

            $this->logger->debug("Batch fetch completed", ['count' => count($urls)]);
        } catch (\Throwable $e) {
            $this->logger->error("Pool execution failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to fetch URLs: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetch a single URL
     */
    public function get(string $url): ResponseInterface
    {
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        try {
            $this->logger->debug("Fetching single URL: {$url}");
            $response = $this->sendAsync($url)->wait();
            $this->logger->debug("Fetched {$url}", ['status' => $response->getStatusCode()]);
            return $response;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch {$url}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send a GET request with the per-request safety options (size limit, proxy rotation)
     */
    private function sendAsync(string $url, array $options = []): PromiseInterface
    {
        $sink = null;
        if ($this->maxResponseSize > 0) {
            $limit = $this->maxResponseSize;
            $sink = new LimitedStream($limit);
            $options['sink'] = $sink;
            // Refuse early when the server announces a body that is too large
            $options['on_headers'] = static function (ResponseInterface $response) use ($limit, $url): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && ctype_digit($length) && (int)$length > $limit) {
                    throw new ResponseTooLargeException(
                        "Response from {$url} is larger than the {$limit} byte limit ({$length} bytes)"
                    );
                }
            };
        }

        if (!empty($this->proxies)) {
            $options['proxy'] = $this->proxies[$this->proxyIndex++ % count($this->proxies)];
        }

        $promise = $this->client->sendAsync(new Request('GET', $url), $options);

        if ($sink === null) {
            return $promise;
        }

        $tooLarge = static function (?\Throwable $previous = null) use ($sink, $url): PromiseInterface {
            return Create::rejectionFor(new ResponseTooLargeException(
                "Response from {$url} is larger than the {$sink->getLimit()} byte limit",
                0,
                $previous
            ));
        };

        return $promise->then(static function ($response) use ($sink, $tooLarge) {
            // Handlers that do not abort on a short write (e.g. the stream handler) truncate instead
            return $sink->limitExceeded() ? $tooLarge() : $response;
        }, static function ($reason) use ($sink, $tooLarge) {
            if ($sink->limitExceeded()) {
                return $tooLarge($reason instanceof \Throwable ? $reason : null);
            }
            if ($reason instanceof RequestException && $reason->getPrevious() instanceof ResponseTooLargeException) {
                return Create::rejectionFor($reason->getPrevious());
            }
            return Create::rejectionFor($reason);
        });
    }

    /**
     * Validate URL format and scheme
     */
    private function isValidUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Get concurrency setting
     */
    public function getConcurrency(): int
    {
        return $this->concurrency;
    }
}
