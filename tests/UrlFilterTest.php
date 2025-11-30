<?php

namespace IProDev\Sitemap\Tests;

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\Filter\UrlFilter;

class UrlFilterTest extends TestCase
{
    public function testShouldCrawlWithExclude(): void
    {
        $filter = new UrlFilter([
            'exclude' => ['/admin/*', '*.pdf']
        ]);

        $this->assertTrue($filter->shouldCrawl('https://example.com/page'));
        $this->assertFalse($filter->shouldCrawl('https://example.com/admin/page'));
        $this->assertFalse($filter->shouldCrawl('https://example.com/file.pdf'));
    }

    public function testShouldCrawlWithInclude(): void
    {
        $filter = new UrlFilter([
            'include' => ['/blog/*', '/products/*']
        ]);

        $this->assertTrue($filter->shouldCrawl('https://example.com/blog/post'));
        $this->assertTrue($filter->shouldCrawl('https://example.com/products/item'));
        $this->assertFalse($filter->shouldCrawl('https://example.com/about'));
    }

    public function testShouldCrawlWithBoth(): void
    {
        $filter = new UrlFilter([
            'include' => ['/blog/*'],
            'exclude' => ['/blog/private/*']
        ]);

        $this->assertTrue($filter->shouldCrawl('https://example.com/blog/post'));
        $this->assertFalse($filter->shouldCrawl('https://example.com/blog/private/post'));
    }

    public function testGetPriority(): void
    {
        $filter = new UrlFilter([
            'priority' => [
                'homepage' => 1.0,
                '/products/*' => 0.8,
                '/blog/*' => 0.6
            ]
        ]);

        $this->assertEquals(1.0, $filter->getPriority('https://example.com/homepage'));
        $this->assertEquals(0.8, $filter->getPriority('https://example.com/products/item'));
        $this->assertEquals(0.6, $filter->getPriority('https://example.com/blog/post'));
        $this->assertEquals(0.5, $filter->getPriority('https://example.com/other'));
    }

    public function testFilterUrls(): void
    {
        $filter = new UrlFilter([
            'exclude' => ['/admin/*']
        ]);

        $urls = [
            'https://example.com/page1',
            'https://example.com/admin/page',
            'https://example.com/page2'
        ];

        $filtered = $filter->filterUrls($urls);

        $this->assertCount(2, $filtered);
        $this->assertContains('https://example.com/page1', $filtered);
        $this->assertContains('https://example.com/page2', $filtered);
    }

    public function testFilterPages(): void
    {
        $filter = new UrlFilter([
            'exclude' => ['/admin/*'],
            'priority' => ['/products/*' => 0.8]
        ]);

        $pages = [
            ['url' => 'https://example.com/page1'],
            ['url' => 'https://example.com/admin/page'],
            ['url' => 'https://example.com/products/item']
        ];

        $filtered = $filter->filterPages($pages);

        $this->assertCount(2, $filtered);
        $this->assertEquals(0.5, $filtered[0]['priority']);
        $this->assertEquals(0.8, $filtered[1]['priority']);
    }

    public function testWildcardMatching(): void
    {
        $filter = new UrlFilter([
            'exclude' => ['/api/v*/test']
        ]);

        $this->assertFalse($filter->shouldCrawl('https://example.com/api/v1/test'));
        $this->assertFalse($filter->shouldCrawl('https://example.com/api/v2/test'));
        $this->assertTrue($filter->shouldCrawl('https://example.com/api/v1/other'));
    }

    public function testCaseSensitivity(): void
    {
        $filter = new UrlFilter([
            'exclude' => ['/Admin/*'],
            'case_sensitive' => false
        ]);

        $this->assertFalse($filter->shouldCrawl('https://example.com/admin/page'));
        $this->assertFalse($filter->shouldCrawl('https://example.com/ADMIN/page'));
    }
}
