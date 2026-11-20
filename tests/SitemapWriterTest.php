<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\Sitemap\ImageSitemapWriter;
use IProDev\Sitemap\SitemapWriter;
use PHPUnit\Framework\TestCase;

class SitemapWriterTest extends TestCase
{
    private string $outDir;

    protected function setUp(): void
    {
        $this->outDir = sys_get_temp_dir() . '/sitemap_writer_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->outDir)) {
            rmdir($this->outDir);
        }
    }

    private function page(string $url, ?string $lastmod = null): array
    {
        return ['url' => $url, 'status' => 200, 'lastmod' => $lastmod];
    }

    private function readGzip(string $file): string
    {
        $content = gzdecode((string)file_get_contents($file));
        $this->assertIsString($content);
        return $content;
    }

    public function testAmpersandInUrlIsEscapedExactlyOnce(): void
    {
        $url = 'https://example.com/search?a=1&b=2';
        $files = SitemapWriter::write([$this->page($url)], $this->outDir);

        $raw = $this->readGzip($files[0]);
        $this->assertStringContainsString('<loc>https://example.com/search?a=1&amp;b=2</loc>', $raw);
        $this->assertStringNotContainsString('&amp;amp;', $raw);

        $xml = simplexml_load_string($raw);
        $this->assertSame($url, (string)$xml->url[0]->loc);
    }

    public function testLastmodIsWritten(): void
    {
        $files = SitemapWriter::write([$this->page('https://example.com/', '2026-01-15')], $this->outDir);

        $xml = simplexml_load_string($this->readGzip($files[0]));
        $this->assertSame('2026-01-15', (string)$xml->url[0]->lastmod);
    }

    public function testIndexUsesOriginOfPagesWhenNoPublicBaseIsGiven(): void
    {
        $files = SitemapWriter::write([$this->page('https://example.com:8443/a/b')], $this->outDir);

        $index = simplexml_load_file(end($files));
        $this->assertSame('https://example.com:8443/sitemap-1.xml.gz', (string)$index->sitemap[0]->loc);
    }

    public function testIndexUsesPublicBase(): void
    {
        $files = SitemapWriter::write(
            [$this->page('https://example.com/')],
            $this->outDir,
            50000,
            'https://cdn.example.com/sitemaps/'
        );

        $index = simplexml_load_file(end($files));
        $this->assertSame('https://cdn.example.com/sitemaps/sitemap-1.xml.gz', (string)$index->sitemap[0]->loc);
    }

    public function testIndexListsEveryChunk(): void
    {
        $pages = [
            $this->page('https://example.com/1'),
            $this->page('https://example.com/2'),
            $this->page('https://example.com/3'),
        ];
        $files = SitemapWriter::write($pages, $this->outDir, 2);

        $this->assertCount(3, $files); // two chunks + index
        $index = simplexml_load_file(end($files));
        $this->assertCount(2, $index->sitemap);
        $this->assertSame('https://example.com/sitemap-2.xml.gz', (string)$index->sitemap[1]->loc);
    }

    public function testIndexLastmodUsesTheRealTimezoneOffset(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tehran'); // UTC+03:30, no DST

        try {
            $files = SitemapWriter::write([$this->page('https://example.com/')], $this->outDir);
            $index = simplexml_load_file(end($files));
            $lastmod = (string)$index->sitemap[0]->lastmod;

            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+03:30$/', $lastmod);
            $this->assertSame(filemtime($files[0]), (new \DateTimeImmutable($lastmod))->getTimestamp());
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testImageSitemapEscapesOnce(): void
    {
        $files = ImageSitemapWriter::write([[
            'url' => 'https://example.com/?a=1&b=2',
            'images' => [['url' => 'https://example.com/i.png?w=1&h=2', 'title' => 'Tom & Jerry']],
        ]], $this->outDir);

        $raw = $this->readGzip($files[0]);
        $this->assertStringNotContainsString('&amp;amp;', $raw);

        $xml = simplexml_load_string($raw);
        $image = $xml->url[0]->children('http://www.google.com/schemas/sitemap-image/1.1')->image;
        $this->assertSame('https://example.com/i.png?w=1&h=2', (string)$image->loc);
        $this->assertSame('Tom & Jerry', (string)$image->title);
    }
}
