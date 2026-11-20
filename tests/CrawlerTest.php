<?php

namespace IProDev\Sitemap\Tests;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use IProDev\Sitemap\CrawlCheckpoint;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Filter\UrlFilter;
use IProDev\Sitemap\RateLimiter;
use IProDev\Sitemap\RobotsTxt;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class CrawlerTest extends TestCase
{
    /** @var array<string, int> request count per URL */
    private array $requests = [];

    /**
     * A small fake site; keys are path + query
     */
    private function site(): array
    {
        $html = fn(string $body, string $head = '') => new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            "<html><head>{$head}</head><body>{$body}</body></html>"
        );

        return [
            '/' => $html(
                '<a href="/a">a</a> <a href="https://EXAMPLE.com:443/a?utm_source=newsletter#top">a again</a>'
                . ' <a href="/b">b</a>'
                . ' <a href="/noindex">n</a> <a href="/dup">d</a> <a href="/ext-canon">e</a>'
                . ' <a href="/offsite">o</a> <a href="/missing">m</a> <a href="/nofollow-page">nf</a>'
                . ' <a href="/private/secret">p</a> <a href="/admin/x">x</a>'
                . ' <a rel="nofollow" href="/rel-nofollow">r</a> <a href="/header-noindex">h</a>'
                . ' <a href="/latin1">l</a> <a href="https://other.example/">external</a>',
                '<title>Home</title>'
            ),
            '/a' => $html('<h1>A</h1><p>one two three</p>', '<title>Page A</title>'),
            '/b' => new Response(301, ['Location' => '/c']),
            '/c' => $html('<a href="/">home</a>', '<title>C</title>'),
            '/noindex' => $html('<a href="/from-noindex">x</a>', '<meta name="robots" content="noindex, follow">'),
            '/from-noindex' => $html('found through a noindex page'),
            '/dup' => $html('duplicate', '<link rel="canonical" href="/a">'),
            '/ext-canon' => $html('syndicated', '<link rel="canonical" href="https://other.example/original">'),
            '/offsite' => new Response(302, ['Location' => 'https://other.example/landing']),
            '/missing' => new Response(404, [], 'not found'),
            '/nofollow-page' => $html(
                '<a href="/only-from-nofollow">x</a>',
                '<meta name="robots" content="nofollow">'
            ),
            '/only-from-nofollow' => $html('should never be fetched'),
            '/private/secret' => $html('blocked by robots.txt'),
            '/admin/x' => $html('excluded by filter'),
            '/rel-nofollow' => $html('only linked with rel=nofollow'),
            '/header-noindex' => new Response(
                200,
                ['Content-Type' => 'text/html', 'X-Robots-Tag' => 'googlebot: noindex'],
                'x'
            ),
            '/latin1' => new Response(
                200,
                ['Content-Type' => 'text/html; charset=windows-1252'],
                "<html><head><title>\xe9t\xe9</title></head><body>caf\xe9</body></html>"
            ),
        ];
    }

    private function crawler(array $site, ?UrlFilter $filter = null): Crawler
    {
        $handler = function (RequestInterface $request) use ($site) {
            $uri = $request->getUri();
            $key = $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');
            $full = $uri->getHost() . $key;
            $this->requests[$full] = ($this->requests[$full] ?? 0) + 1;

            if ($uri->getHost() !== 'example.com') {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'text/html'], 'other site'));
            }

            return Create::promiseFor($site[$key] ?? new Response(404));
        };

        $fetcher = new Fetcher(['handler' => HandlerStack::create($handler)]);
        $robots = RobotsTxt::fromString("User-agent: *\nDisallow: /private/\n");
        $crawler = new Crawler($fetcher, $robots);
        $crawler->setUrlFilter($filter ?? new UrlFilter(['exclude' => ['/admin/*']]));

        return $crawler;
    }

    public function testOnlyIndexableCanonicalPagesAreListed(): void
    {
        $pages = $this->crawler($this->site())->crawl('https://Example.com', 100, 5);
        $urls = array_column($pages, 'url');
        sort($urls);

        $this->assertSame([
            'https://example.com/',
            'https://example.com/a',
            'https://example.com/c',
            'https://example.com/from-noindex',
            'https://example.com/latin1',
            'https://example.com/nofollow-page',
        ], $urls);
    }

    public function testEveryUrlIsFetchedAtMostOnce(): void
    {
        $this->crawler($this->site())->crawl('https://example.com/', 100, 5);

        foreach ($this->requests as $url => $count) {
            $this->assertSame(1, $count, "{$url} was fetched {$count} times");
        }
    }

    public function testBlockedExcludedAndNofollowLinksAreNotFetched(): void
    {
        $this->crawler($this->site())->crawl('https://example.com/', 100, 5);

        foreach (['/private/secret', '/admin/x', '/rel-nofollow', '/only-from-nofollow'] as $path) {
            $this->assertArrayNotHasKey('example.com' . $path, $this->requests, $path);
        }
        // Host case, default port, fragment and tracking parameters are normalized away
        $this->assertSame(1, $this->requests['example.com/a']);
        $this->assertArrayNotHasKey('example.com/a?utm_source=newsletter', $this->requests);
        // Links to other hosts are not followed (only the off-site redirect reached other.example)
        $this->assertArrayNotHasKey('other.example/', $this->requests);
    }

    public function testErrorsAreRecordedWithReferrer(): void
    {
        $crawler = $this->crawler($this->site());
        $crawler->crawl('https://example.com/', 100, 5);

        $this->assertSame([[
            'url' => 'https://example.com/missing',
            'status' => 404,
            'error' => 'HTTP 404',
            'referrer' => 'https://example.com/',
        ]], $crawler->getErrors());

        $skipped = $crawler->getStats()['skipped'];
        $this->assertSame(2, $skipped['noindex']);
        $this->assertSame(2, $skipped['canonical']);
        $this->assertSame(1, $skipped['offsite_redirect']);
        $this->assertSame(1, $skipped['excluded']);
    }

    public function testPageDataIsCollected(): void
    {
        $pages = $this->crawler($this->site())->crawl('https://example.com/', 100, 5);
        $byUrl = array_column($pages, null, 'url');

        $a = $byUrl['https://example.com/a'];
        $this->assertSame('Page A', $a['title']);
        $this->assertSame('A', $a['h1']);
        $this->assertSame(4, $a['word_count']);
        $this->assertSame(200, $a['status']);
        $this->assertSame(1, $a['depth']);
        $this->assertSame(64, strlen($a['content_hash']));

        // Converted from windows-1252 before parsing
        $this->assertSame('été', $byUrl['https://example.com/latin1']['title']);
    }

    public function testMaxDepthAndMaxPages(): void
    {
        $site = [];
        for ($i = 0; $i < 20; $i++) {
            $next = '<a href="/p' . ($i + 1) . '">next</a>';
            $site["/p{$i}"] = new Response(200, ['Content-Type' => 'text/html'], $next);
        }
        $site['/'] = new Response(200, ['Content-Type' => 'text/html'], '<a href="/p0">p0</a>');

        $this->assertCount(3, $this->crawler($site)->crawl('https://example.com/', 100, 2));
        $this->assertCount(5, $this->crawler($site)->crawl('https://example.com/', 5, 50));
    }

    public function testPageProcessorsCanAddFields(): void
    {
        $crawler = $this->crawler($this->site());
        $crawler->addPageProcessor(fn(array $page, string $html) => ['html_length' => strlen($html)]);

        $pages = $crawler->crawl('https://example.com/', 1, 0);

        $this->assertGreaterThan(0, $pages[0]['html_length']);
    }

    public function testRateLimiterIsAppliedToEveryRequest(): void
    {
        $limiter = new RateLimiter(1000, 60, 1);
        $crawler = $this->crawler($this->site());
        $crawler->setRateLimiter($limiter);
        $crawler->crawl('https://example.com/', 100, 5);

        $this->assertSame($crawler->getStats()['processed'], $limiter->getRequestCount('example.com'));
    }

    public function testInterruptedCrawlResumesFromCheckpoint(): void
    {
        $file = sys_get_temp_dir() . '/crawler_checkpoint_' . uniqid() . '.json';
        $site = $this->site();

        try {
            // First run: stop after the first page has been processed
            $first = $this->crawler($site);
            $first->setCheckpoint(new CrawlCheckpoint($file, 1000));
            $first->addPageProcessor(function () use ($first) {
                $first->requestStop();
                return null;
            });
            $partial = $first->crawl('https://example.com/', 100, 5);

            $this->assertTrue($first->isInterrupted());
            $this->assertCount(1, $partial);
            $this->assertFileExists($file);

            // Second run resumes and finishes without fetching anything twice
            $second = $this->crawler($site);
            $second->setCheckpoint(new CrawlCheckpoint($file, 1000), true);
            $pages = $second->crawl('https://example.com/', 100, 5);

            $this->assertFalse($second->isInterrupted());
            $this->assertCount(6, $pages);
            $this->assertFileDoesNotExist($file);
            foreach ($this->requests as $url => $count) {
                $this->assertSame(1, $count, "{$url} was fetched {$count} times");
            }
        } finally {
            @unlink($file);
        }
    }
}
