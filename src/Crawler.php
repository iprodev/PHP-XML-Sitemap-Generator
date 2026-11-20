<?php

namespace IProDev\Sitemap;

use GuzzleHttp\Exception\RequestException;
use IProDev\Sitemap\Filter\UrlFilter;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Breadth-first crawler for a single host.
 *
 * Pages end up in the result only if they are indexable: a 2xx response on the
 * crawled host (after redirects), not noindex (meta robots or X-Robots-Tag),
 * and either without a canonical link or canonical to themselves. A page whose
 * canonical points to another URL on the same host makes the crawler fetch and
 * list that canonical URL instead.
 *
 * Each page record contains: url, status, lastmod, depth, content_type,
 * content_size, response_time, content_hash, title, meta_description, h1,
 * lang, word_count, image_count, link_count, plus whatever page processors add.
 */
class Crawler
{
    private const REDIRECT_HISTORY_HEADER = 'X-Guzzle-Redirect-History';
    private const DEFAULT_BATCH_SIZE = 50;

    private Fetcher $fetcher;
    private RobotsTxt $robots;
    private LoggerInterface $logger;
    private ?UrlFilter $filter = null;
    private ?RateLimiter $rateLimiter = null;
    private ?CrawlCheckpoint $checkpoint = null;
    private bool $resume = false;
    /** @var callable[] */
    private array $pageProcessors = [];

    private string $startUrl = '';
    private string $host = '';
    private int $maxPages = 0;
    private int $maxDepth = 0;

    /** @var array<string, array> indexable pages by URL */
    private array $seen = [];
    /** @var array<string, true> normalized URLs that were queued, fetched or rejected */
    private array $known = [];
    /** @var array<int, array{url: string, depth: int, referrer: ?string}> */
    private array $queue = [];
    /** @var array<int, array{url: string, status: ?int, error: string, referrer: ?string}> */
    private array $errors = [];
    private int $processed = 0;
    /** @var array<string, int> */
    private array $skipped = [];
    private bool $stopRequested = false;
    private bool $interrupted = false;
    private int $lastCheckpointAt = 0;

    public function __construct(Fetcher $fetcher, RobotsTxt $robots, ?LoggerInterface $logger = null)
    {
        $this->fetcher = $fetcher;
        $this->robots = $robots;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * URLs matching the filter's exclude patterns are not fetched at all
     */
    public function setUrlFilter(?UrlFilter $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Throttle requests. With a fixed delay requests are sent one at a time.
     */
    public function setRateLimiter(?RateLimiter $rateLimiter): self
    {
        $this->rateLimiter = $rateLimiter;
        return $this;
    }

    /**
     * Save progress periodically and when the crawl is stopped; with $resume, continue
     * from a checkpoint of the same start URL.
     */
    public function setCheckpoint(?CrawlCheckpoint $checkpoint, bool $resume = false): self
    {
        $this->checkpoint = $checkpoint;
        $this->resume = $resume;
        return $this;
    }

    /**
     * Add a callable that receives each indexable page and can add fields to its record:
     * function (array $page, string $html, ResponseInterface $response): ?array
     * ($html is the UTF-8 body for HTML pages, '' otherwise)
     */
    public function addPageProcessor(callable $processor): self
    {
        $this->pageProcessors[] = $processor;
        return $this;
    }

    /**
     * Ask a running crawl to stop after the current batch (e.g. from a signal handler)
     */
    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    /**
     * Whether the last crawl was stopped before it finished
     */
    public function isInterrupted(): bool
    {
        return $this->interrupted;
    }

    /**
     * Crawl starting from $startUrl up to $maxPages and $maxDepth within same host.
     * @return array<int, array> indexable pages
     */
    public function crawl(string $startUrl, int $maxPages = 10000, int $maxDepth = 5): array
    {
        if ($maxPages < 1) {
            throw new \InvalidArgumentException('maxPages must be at least 1');
        }
        if ($maxDepth < 0) {
            throw new \InvalidArgumentException('maxDepth must be non-negative');
        }

        $host = UrlNormalizer::host($startUrl);
        if ($host === null) {
            throw new \InvalidArgumentException('Invalid start URL');
        }

        $this->startUrl = UrlNormalizer::normalize($startUrl);
        $this->host = $host;
        $this->maxPages = $maxPages;
        $this->maxDepth = $maxDepth;
        $this->seen = [];
        $this->known = [];
        $this->queue = [];
        $this->errors = [];
        $this->processed = 0;
        $this->skipped = ['noindex' => 0, 'canonical' => 0, 'offsite_redirect' => 0, 'robots' => 0, 'excluded' => 0];
        $this->interrupted = false;
        $this->lastCheckpointAt = 0;

        if (!$this->restoreCheckpoint()) {
            $this->queue = [['url' => $this->startUrl, 'depth' => 0, 'referrer' => null]];
            $this->known = [$this->startUrl => true];
        }

        $this->logger->info("Starting crawl from {$this->startUrl}", [
            'maxPages' => $maxPages,
            'maxDepth' => $maxDepth
        ]);

        while (!empty($this->queue) && count($this->seen) < $maxPages) {
            if ($this->stopRequested) {
                $this->interrupted = true;
                break;
            }

            $batch = array_splice($this->queue, 0, $this->batchSize());

            if ($this->rateLimiter !== null) {
                foreach ($batch as $unused) {
                    $this->rateLimiter->throttle($this->host);
                }
            }

            $this->logger->debug("Fetching batch of " . count($batch) . " URLs");

            try {
                $this->fetcher->fetchMany(
                    array_column($batch, 'url'),
                    function (ResponseInterface $response, $idx, ?float $transferTime = null) use ($batch) {
                        $this->handleResponse($response, $batch[$idx], $transferTime);
                    },
                    function ($reason, $idx) use ($batch) {
                        $this->recordFailure($batch[$idx], $reason);
                    }
                );
            } catch (\Throwable $e) {
                $this->logger->error("Error during batch fetch", ['error' => $e->getMessage()]);
            }

            $uniqueUrls = count($this->seen);
            $this->logger->info(
                "Progress: {$this->processed} pages processed, {$uniqueUrls}/{$maxPages} indexable URLs found"
            );

            $this->maybeSaveCheckpoint();
        }

        $this->stopRequested = false;

        if ($this->interrupted) {
            $this->saveCheckpoint();
            $this->logger->warning('Crawl interrupted', [
                'processed' => $this->processed,
                'checkpoint_saved' => $this->checkpoint !== null
            ]);
        } elseif ($this->checkpoint !== null) {
            $this->checkpoint->delete();
        }

        $this->logger->info("Crawl completed", [
            'totalPages' => count($this->seen),
            'processed' => $this->processed,
            'errors' => count($this->errors),
            'skipped' => array_filter($this->skipped)
        ]);

        return array_values($this->seen);
    }

    private function batchSize(): int
    {
        $size = self::DEFAULT_BATCH_SIZE;
        if ($this->rateLimiter !== null) {
            // With a fixed delay, requests have to be sent one after another
            $size = $this->rateLimiter->getDelayMs() > 0 ? 1 : $this->fetcher->getConcurrency();
        }

        return max(1, min($size, $this->maxPages - count($this->seen)));
    }

    private function handleResponse(ResponseInterface $response, array $item, ?float $transferTime): void
    {
        $this->processed++;

        $requested = $item['url'];
        $status = $response->getStatusCode();
        $url = UrlNormalizer::normalize($this->effectiveUrl($response, $requested));
        $this->known[$url] = true;

        if (!$this->isSameHost($url)) {
            $this->skipped['offsite_redirect']++;
            $this->logger->debug("Skipping {$requested}: redirects to another host ({$url})");
            return;
        }

        if ($status >= 400) {
            $this->errors[] = [
                'url' => $requested,
                'status' => $status,
                'error' => "HTTP {$status}",
                'referrer' => $item['referrer'] ?? null
            ];
            $this->logger->debug("Skipping {$requested} with status {$status}");
            return;
        }

        if ($status < 200 || $status >= 300) {
            // e.g. a redirect that was not followed
            $this->logger->debug("Skipping {$requested} with status {$status}");
            return;
        }

        if ($url !== $requested && !$this->robots->isAllowed($url)) {
            $this->skipped['robots']++;
            $this->logger->debug("Skipping {$requested}: redirect target {$url} is blocked by robots.txt");
            return;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $rawBody = (string)$response->getBody();
        $directives = $this->headerRobotsDirectives($response);

        $html = '';
        $document = null;
        if ($this->isHtmlContent($contentType, $rawBody)) {
            $html = Parser::toUtf8($rawBody, $contentType);
            $document = Parser::parseDocument($html, $url);
            $directives = array_merge($directives, $document['robots']);
        }

        $noindex = (bool)array_intersect($directives, ['noindex', 'none']);
        $nofollow = (bool)array_intersect($directives, ['nofollow', 'none']);

        if ($document !== null && !$nofollow && $item['depth'] < $this->maxDepth) {
            foreach ($document['links'] as $link) {
                $this->enqueue($link, $item['depth'] + 1, $url);
            }
        }

        if ($noindex) {
            $this->skipped['noindex']++;
            $this->logger->debug("Skipping {$url}: noindex");
            return;
        }

        if ($document !== null && $document['canonical'] !== null) {
            $canonical = UrlNormalizer::normalize($document['canonical']);
            if ($canonical !== $url) {
                // A duplicate of another page: list the canonical URL (fetched on its own) instead
                $this->skipped['canonical']++;
                if ($this->isSameHost($canonical)) {
                    $this->enqueue($canonical, $item['depth'], $url);
                }
                $this->logger->debug("Skipping {$url}: canonical URL is {$canonical}");
                return;
            }
        }

        if (isset($this->seen[$url])) {
            return;
        }

        $page = [
            'url' => $url,
            'status' => $status,
            'lastmod' => $this->extractLastModified($response),
            'depth' => $item['depth'],
            'content_type' => $contentType !== '' ? $contentType : null,
            'content_size' => strlen($rawBody),
            'response_time' => $transferTime !== null ? round($transferTime, 3) : null,
            'content_hash' => $document !== null ? ChangeDetector::calculateHash($html) : hash('sha256', $rawBody),
            'title' => $document['title'] ?? null,
            'meta_description' => $document['meta_description'] ?? null,
            'h1' => $document['h1'] ?? null,
            'lang' => $document['lang'] ?? null,
            'word_count' => $document['word_count'] ?? null,
            'image_count' => $document['image_count'] ?? null,
            'link_count' => $document['link_count'] ?? null,
        ];

        foreach ($this->pageProcessors as $processor) {
            try {
                $extra = $processor($page, $html, $response);
                if (is_array($extra)) {
                    $page = array_merge($page, $extra);
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Page processor failed for {$url}", ['error' => $e->getMessage()]);
            }
        }

        $this->seen[$url] = $page;
    }

    /**
     * @param mixed $reason
     */
    private function recordFailure(array $item, $reason): void
    {
        $this->processed++;
        $this->known[$item['url']] = true;

        $message = $reason instanceof \Throwable ? $reason->getMessage() : (string)$reason;
        $status = null;
        if ($reason instanceof RequestException && $reason->getResponse() !== null) {
            $status = $reason->getResponse()->getStatusCode();
        }

        $this->errors[] = [
            'url' => $item['url'],
            'status' => $status,
            'error' => $message,
            'referrer' => $item['referrer'] ?? null
        ];
        $this->logger->warning("Failed to fetch {$item['url']}", ['reason' => $message]);
    }

    private function enqueue(string $url, int $depth, ?string $referrer): void
    {
        $url = UrlNormalizer::normalize($url);

        if (isset($this->known[$url]) || !$this->isSameHost($url)) {
            return;
        }

        if (!$this->robots->isAllowed($url)) {
            $this->known[$url] = true;
            $this->skipped['robots']++;
            $this->logger->debug("URL blocked by robots.txt: {$url}");
            return;
        }

        if ($this->filter !== null && $this->filter->isExcluded($url)) {
            $this->known[$url] = true;
            $this->skipped['excluded']++;
            return;
        }

        // Keep some slack for pages that turn out not to be indexable, but bound the queue
        if (count($this->queue) + count($this->seen) >= $this->maxPages * 2) {
            return;
        }

        $this->known[$url] = true;
        $this->queue[] = ['url' => $url, 'depth' => $depth, 'referrer' => $referrer];
    }

    private function effectiveUrl(ResponseInterface $response, string $requested): string
    {
        $history = $response->getHeader(self::REDIRECT_HISTORY_HEADER);
        return empty($history) ? $requested : (string)end($history);
    }

    private function isSameHost(string $url): bool
    {
        return UrlNormalizer::host($url) === $this->host;
    }

    /**
     * Directives from X-Robots-Tag headers; "googlebot: noindex" counts as "noindex"
     *
     * @return string[]
     */
    private function headerRobotsDirectives(ResponseInterface $response): array
    {
        $directives = [];
        foreach ($response->getHeader('X-Robots-Tag') as $value) {
            foreach (explode(',', strtolower($value)) as $part) {
                $part = trim($part);
                if (!str_starts_with($part, 'unavailable_after') && preg_match('/^[\w-]+\s*:\s*(.+)$/', $part, $m)) {
                    $part = trim($m[1]);
                }
                $directives[] = $part;
            }
        }

        return $directives;
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

    private function isHtmlContent(string $contentType, string $body): bool
    {
        if ($contentType === '') {
            return preg_match('/<html[\s>]/i', substr($body, 0, 1024)) === 1;
        }

        return stripos($contentType, 'text/html') !== false ||
               stripos($contentType, 'application/xhtml+xml') !== false;
    }

    private function restoreCheckpoint(): bool
    {
        if ($this->checkpoint === null || !$this->resume) {
            return false;
        }

        $state = $this->checkpoint->load();
        if (!is_array($state)) {
            return false;
        }

        if (($state['start_url'] ?? null) !== $this->startUrl || !is_array($state['queue'] ?? null)) {
            $this->logger->warning('Ignoring checkpoint: it belongs to a different start URL or is invalid');
            return false;
        }

        $this->queue = array_values(array_filter(
            $state['queue'],
            fn($item) => is_array($item) && isset($item['url'], $item['depth']) && is_string($item['url'])
        ));
        $this->known = array_fill_keys(array_filter((array)($state['known'] ?? []), 'is_string'), true);
        $this->seen = array_filter((array)($state['seen'] ?? []), 'is_array');
        $this->errors = array_values(array_filter((array)($state['errors'] ?? []), 'is_array'));
        $this->processed = (int)($state['processed'] ?? 0);
        $this->skipped = array_merge($this->skipped, array_map('intval', (array)($state['skipped'] ?? [])));
        $this->lastCheckpointAt = $this->processed;

        $this->logger->info('Resuming crawl from checkpoint', [
            'pages' => count($this->seen),
            'queued' => count($this->queue),
            'processed' => $this->processed
        ]);

        return true;
    }

    private function maybeSaveCheckpoint(): void
    {
        if ($this->checkpoint === null) {
            return;
        }

        if ($this->processed - $this->lastCheckpointAt >= max(1, $this->checkpoint->getInterval())) {
            $this->saveCheckpoint();
        }
    }

    private function saveCheckpoint(): void
    {
        if ($this->checkpoint === null) {
            return;
        }

        $this->checkpoint->save([
            'start_url' => $this->startUrl,
            'queue' => $this->queue,
            'known' => array_keys($this->known),
            'seen' => $this->seen,
            'errors' => $this->errors,
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'pages_crawled' => count($this->seen),
        ]);
        $this->lastCheckpointAt = $this->processed;
        $this->logger->debug('Checkpoint saved', ['pages' => count($this->seen), 'queued' => count($this->queue)]);
    }

    /**
     * Pages and requests that failed: HTTP status >= 400 or a transport error
     *
     * @return array<int, array{url: string, status: ?int, error: string, referrer: ?string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStats(): array
    {
        return [
            'processed' => $this->processed,
            'unique_urls' => count($this->seen),
            'queued' => count($this->queue),
            'errors' => count($this->errors),
            'skipped' => $this->skipped,
        ];
    }
}
