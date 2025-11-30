<?php

namespace IProDev\Sitemap;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Crawler
{
    private Fetcher $fetcher;
    private RobotsTxt $robots;
    private LoggerInterface $logger;
    private string $host = '';
    /** @var array<string, array{url:string,status:int,lastmod:?string}> */
    private array $seen = [];
    /** @var array<int, array{url:string, depth:int}> */
    private array $queue = [];
    private int $processed = 0;

    public function __construct(Fetcher $fetcher, RobotsTxt $robots, ?LoggerInterface $logger = null)
    {
        $this->fetcher = $fetcher;
        $this->robots = $robots;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Crawl starting from $startUrl up to $maxPages and $maxDepth within same host.
     * @return array<int,array{url:string,status:int,lastmod:?string}>
     */
    public function crawl(string $startUrl, int $maxPages = 10000, int $maxDepth = 5): array
    {
        // Validate inputs
        if ($maxPages < 1) {
            throw new \InvalidArgumentException('maxPages must be at least 1');
        }
        if ($maxDepth < 0) {
            throw new \InvalidArgumentException('maxDepth must be non-negative');
        }

        $parsedUrl = parse_url($startUrl);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            throw new \InvalidArgumentException('Invalid start URL');
        }

        $this->host = $parsedUrl['host'];
        $this->queue = [['url' => $startUrl, 'depth' => 0]];
        $this->seen = [];
        $this->processed = 0;

        $this->logger->info("Starting crawl from {$startUrl}", [
            'maxPages' => $maxPages,
            'maxDepth' => $maxDepth
        ]);

        while (!empty($this->queue) && count($this->seen) < $maxPages) {
            $remaining = $maxPages - count($this->seen);
            $batchSize = min($remaining, 50);
            $batch = array_splice($this->queue, 0, $batchSize);
            $urls = array_map(fn($x) => $x['url'], $batch);

            $this->logger->debug("Fetching batch of " . count($urls) . " URLs");

            try {
                $this->fetcher->fetchMany(
                    $urls,
                    function (ResponseInterface $response, int $idx) use ($batch, $maxDepth, $maxPages) {
                        $this->handleResponse($response, $batch[$idx], $maxDepth, $maxPages);
                    },
                    function ($reason, int $idx) use ($batch) {
                        $url = $batch[$idx]['url'];
                        $this->logger->warning("Failed to fetch {$url}", ['reason' => (string)$reason]);
                    }
                );
            } catch (\Throwable $e) {
                $this->logger->error("Error during batch fetch", ['error' => $e->getMessage()]);
            }

            $uniqueUrls = count($this->seen);
            $this->logger->info(
                "Progress: {$this->processed}/{$maxPages} pages processed, {$uniqueUrls} unique URLs found"
            );
        }

        $this->logger->info("Crawl completed", [
            'totalPages' => count($this->seen),
            'processed' => $this->processed
        ]);

        return array_values($this->seen);
    }

    private function handleResponse(ResponseInterface $response, array $item, int $maxDepth, int $maxPages): void
    {
        $url = $item['url'];
        $depth = $item['depth'];
        $status = $response->getStatusCode();

        $this->processed++;

        // Skip error responses
        if ($status >= 400) {
            $this->logger->debug("Skipping {$url} with status {$status}");
            return;
        }

        $ctype = $response->getHeaderLine('Content-Type');
        $body = (string)$response->getBody();

        // Track page with lastmod
        $lastmod = $this->extractLastModified($response);
        $canonical = Parser::getCanonical($body, $url) ?? $url;

        // Avoid duplicates
        if (isset($this->seen[$canonical])) {
            $this->logger->debug("Already seen canonical URL: {$canonical}");
            return;
        }

        $this->seen[$canonical] = [
            'url' => $canonical,
            'status' => $status,
            'lastmod' => $lastmod
        ];

        // Only parse HTML pages for links
        if ($depth < $maxDepth && $this->isHtmlContent($ctype)) {
            $this->extractAndQueueLinks($body, $url, $depth, $maxPages);
        }
    }

    private function extractLastModified(ResponseInterface $response): ?string
    {
        if (!$response->hasHeader('Last-Modified')) {
            return null;
        }

        try {
            $date = new \DateTime($response->getHeaderLine('Last-Modified'));
            return $date->format('Y-m-d');
        } catch (\Throwable $e) {
            $this->logger->debug("Failed to parse Last-Modified header", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function isHtmlContent(string $contentType): bool
    {
        return stripos($contentType, 'text/html') !== false ||
               stripos($contentType, 'application/xhtml+xml') !== false;
    }

    private function extractAndQueueLinks(string $body, string $baseUrl, int $currentDepth, int $maxPages): void
    {
        try {
            $links = Parser::extractLinks($body, $baseUrl);
            $addedCount = 0;

            foreach ($links as $link) {
                if ($this->shouldQueueLink($link, $maxPages)) {
                    $this->queue[] = ['url' => $link, 'depth' => $currentDepth + 1];
                    $addedCount++;
                }
            }

            if ($addedCount > 0) {
                $this->logger->debug("Added {$addedCount} new links to queue from {$baseUrl}");
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to extract links from {$baseUrl}", ['error' => $e->getMessage()]);
        }
    }

    private function shouldQueueLink(string $link, int $maxPages): bool
    {
        // Check host
        $linkHost = parse_url($link, PHP_URL_HOST);
        if ($linkHost !== $this->host) {
            return false;
        }

        // Check robots.txt
        if (!$this->robots->isAllowed($link)) {
            $this->logger->debug("URL blocked by robots.txt: {$link}");
            return false;
        }

        // Check if already seen or queued
        if (isset($this->seen[$link])) {
            return false;
        }

        if ($this->inQueue($link)) {
            return false;
        }

        // Check total limit
        if (count($this->seen) + count($this->queue) >= $maxPages) {
            return false;
        }

        return true;
    }

    private function inQueue(string $url): bool
    {
        foreach ($this->queue as $q) {
            if ($q['url'] === $url) {
                return true;
            }
        }
        return false;
    }

    public function getStats(): array
    {
        return [
            'processed' => $this->processed,
            'unique_urls' => count($this->seen),
            'queued' => count($this->queue)
        ];
    }
}
