<?php
namespace IProDev\Sitemap;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;

class Fetcher {
    private Client $client;
    private int $concurrency;
    private ?LoggerInterface $logger;

    public function __construct(array $options = [], LoggerInterface $logger = null) {
        $defaults = [
            'headers' => [
                'User-Agent' => 'php-sitemap-generator/1.0 (+https://github.com/iprodev)'
            ],
            'http_errors' => false,
            'allow_redirects' => true,
            'timeout' => 15
        ];
        $this->client = new Client(array_merge($defaults, $options));
        $this->concurrency = $options['concurrency'] ?? 10;
        $this->logger = $logger;
    }

    /**
     * Fetch multiple URLs concurrently (GET).
     * @param string[] $urls
     * @param callable $onFulfilled function(\Psr\Http\Message\ResponseInterface $response, int $index)
     * @param callable|null $onRejected function($reason, int $index)
     */
    public function fetchMany(array $urls, callable $onFulfilled, callable $onRejected = null): void {
        $requests = function($urls) {
            foreach ($urls as $u) {
                yield new Request('GET', $u);
            }
        };

        $pool = new Pool($this->client, $requests($urls), [
            'concurrency' => $this->concurrency,
            'fulfilled'   => $onFulfilled,
            'rejected'    => $onRejected ?? function($reason, $idx) {}
        ]);

        $pool->promise()->wait();
    }

    public function get(string $url) {
        return $this->client->get($url);
    }
}
