<?php

namespace IProDev\Sitemap;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Fetcher
{
    private Client $client;
    private int $concurrency;
    private LoggerInterface $logger;

    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();

        // Validate concurrency
        $concurrency = $options['concurrency'] ?? 10;
        if ($concurrency < 1 || $concurrency > 100) {
            throw new \InvalidArgumentException('Concurrency must be between 1 and 100');
        }
        $this->concurrency = $concurrency;

        $defaults = [
            'headers' => [
                'User-Agent' => 'php-sitemap-generator/1.0 (+https://github.com/iprodev)',
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

        // Merge user options with defaults
        $mergedOptions = array_merge($defaults, $options);

        // Preserve concurrency in merged options
        $mergedOptions['concurrency'] = $this->concurrency;

        $this->client = new Client($mergedOptions);

        $this->logger->info("Fetcher initialized", [
            'concurrency' => $this->concurrency,
            'timeout' => $mergedOptions['timeout']
        ]);
    }

    /**
     * Fetch multiple URLs concurrently (GET).
     * @param string[] $urls
     * @param callable $onFulfilled function(\Psr\Http\Message\ResponseInterface $response, int $index)
     * @param callable|null $onRejected function($reason, int $index)
     */
    public function fetchMany(array $urls, callable $onFulfilled, ?callable $onRejected = null): void
    {
        if (empty($urls)) {
            $this->logger->debug("No URLs to fetch");
            return;
        }

        // Validate URLs
        $validUrls = [];
        foreach ($urls as $url) {
            if ($this->isValidUrl($url)) {
                $validUrls[] = $url;
            } else {
                $this->logger->warning("Invalid URL skipped: {$url}");
            }
        }

        if (empty($validUrls)) {
            $this->logger->warning("No valid URLs to fetch");
            return;
        }

        $requests = function ($urls) {
            foreach ($urls as $u) {
                yield new Request('GET', $u);
            }
        };

        $defaultRejected = function ($reason, $idx) use ($urls) {
            $url = $urls[$idx] ?? 'unknown';
            $message = $reason instanceof \Exception ? $reason->getMessage() : (string)$reason;
            $this->logger->warning("Request failed for {$url}", ['reason' => $message]);
        };

        try {
            $pool = new Pool($this->client, $requests($validUrls), [
                'concurrency' => $this->concurrency,
                'fulfilled'   => function ($response, $idx) use ($onFulfilled, $validUrls) {
                    $url = $validUrls[$idx] ?? 'unknown';
                    $this->logger->debug("Successfully fetched {$url}", [
                        'status' => $response->getStatusCode()
                    ]);
                    $onFulfilled($response, $idx);
                },
                'rejected'    => $onRejected ?? $defaultRejected
            ]);

            $promise = $pool->promise();
            $promise->wait();

            $this->logger->debug("Batch fetch completed", ['count' => count($validUrls)]);
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
    public function get(string $url)
    {
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        try {
            $this->logger->debug("Fetching single URL: {$url}");
            $response = $this->client->get($url);
            $this->logger->debug("Fetched {$url}", ['status' => $response->getStatusCode()]);
            return $response;
        } catch (RequestException $e) {
            $this->logger->error("Failed to fetch {$url}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
