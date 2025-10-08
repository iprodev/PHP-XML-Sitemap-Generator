<?php
namespace IProDev\Sitemap;

use Psr\Http\Message\ResponseInterface;

class Crawler {
    private Fetcher $fetcher;
    private RobotsTxt $robots;
    private string $host = '';
    /** @var array<string, array{url:string,status:int,lastmod:?string}> */
    private array $seen = [];
    /** @var array<int, array{url:string, depth:int}> */
    private array $queue = [];

    public function __construct(Fetcher $fetcher, RobotsTxt $robots) {
        $this->fetcher = $fetcher;
        $this->robots = $robots;
    }

    /**
     * Crawl starting from $startUrl up to $maxPages and $maxDepth within same host.
     * @return array<int,array{url:string,status:int,lastmod:?string}>
     */
    public function crawl(string $startUrl, int $maxPages = 10000, int $maxDepth = 5): array {
        $this->host = parse_url($startUrl, PHP_URL_HOST) ?? '';
        $this->queue = [['url' => $startUrl, 'depth' => 0]];
        $this->seen = [];

        while (!empty($this->queue) && count($this->seen) < $maxPages) {
            $batch = array_splice($this->queue, 0,  min( max(1, $maxPages - count($this->seen)), 50));
            $urls = array_map(fn($x) => $x['url'], $batch);

            $that = $this;
            $this->fetcher->fetchMany(
                $urls,
                function(ResponseInterface $response, int $idx) use ($batch, $maxDepth, $maxPages, $that) {
                    $url = $batch[$idx]['url'];
                    $depth = $batch[$idx]['depth'];
                    $status = $response->getStatusCode();
                    if ($status >= 400) return;

                    $ctype = $response->getHeaderLine('Content-Type');
                    $body  = (string)$response->getBody();
                    // track page
                    $lastmod = null;
                    if ($response->hasHeader('Last-Modified')) {
                        try { $lastmod = (new \DateTime($response->getHeaderLine('Last-Modified')))->format('Y-m-d'); } catch (\Throwable $e) {}
                    }

                    $canonical = Parser::getCanonical($body, $url) ?? $url;
                    $that->seen[$canonical] = ['url' => $canonical, 'status' => $status, 'lastmod' => $lastmod];

                    // only parse html pages
                    if ($depth < $maxDepth && stripos($ctype, 'text/html') !== false) {
                        $links = Parser::extractLinks($body, $url);
                        foreach ($links as $link) {
                            $host = parse_url($link, PHP_URL_HOST) ?? '';
                            if ($host !== $that->host) continue;
                            if (!$that->robots->isAllowed($link)) continue;
                            if (!isset($that->seen[$link]) && !$that->inQueue($link) && (count($that->seen) + count($that->queue) < $maxPages)) {
                                $that->queue[] = ['url' => $link, 'depth' => $depth + 1];
                            }
                        }
                    }
                }
            );
        }

        // return list
        return array_values($this->seen);
    }

    private function inQueue(string $url): bool {
        foreach ($this->queue as $q) {
            if ($q['url'] === $url) return true;
        }
        return false;
    }
}
