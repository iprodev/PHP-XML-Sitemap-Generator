<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\Analyzer\ContentQualityChecker;
use IProDev\Sitemap\Analyzer\SeoAnalyzer;
use IProDev\Sitemap\Sitemap\ImageSitemapWriter;
use IProDev\Sitemap\Sitemap\NewsSitemapWriter;
use IProDev\Sitemap\Sitemap\VideoSitemapWriter;
use PHPUnit\Framework\TestCase;

class ExtractorsTest extends TestCase
{
    public function testExtractImages(): void
    {
        $html = '<base href="https://cdn.example.com/assets/"><img src="a.png" alt="A &amp; B">'
            . '<img data-src="/lazy.png" src="data:image/gif;base64,R0lGOD"><img src="a.png"><img src="">';

        $images = ImageSitemapWriter::extractImages($html, 'https://example.com/page');

        $this->assertSame([
            ['url' => 'https://cdn.example.com/assets/a.png', 'title' => 'A & B', 'caption' => 'A & B'],
            ['url' => 'https://cdn.example.com/lazy.png', 'title' => null, 'caption' => null],
        ], $images);
    }

    public function testExtractVideosUsesPageMetadataAndSkipsVideosWithoutThumbnail(): void
    {
        $html = '<html><head><title>My video page</title><meta name="description" content="About the video">'
            . '</head><body><iframe src="https://www.youtube-nocookie.com/embed/abcDEF12345"></iframe>'
            . '<iframe src="https://player.vimeo.com/video/123456"></iframe>'
            . '<video poster="/poster.jpg"><source src="/clip.mp4"></video></body></html>';

        $videos = VideoSitemapWriter::extractVideos($html, 'https://example.com/v');

        $this->assertCount(2, $videos); // Vimeo has no thumbnail without og:image
        $this->assertSame('https://www.youtube.com/embed/abcDEF12345', $videos[0]['player_url']);
        $this->assertSame('https://i.ytimg.com/vi/abcDEF12345/hqdefault.jpg', $videos[0]['thumbnail']);
        $this->assertSame('My video page', $videos[0]['title']);
        $this->assertSame('About the video', $videos[0]['description']);
        $this->assertSame('https://example.com/clip.mp4', $videos[1]['content_url']);
        $this->assertSame('https://example.com/poster.jpg', $videos[1]['thumbnail']);
    }

    public function testVimeoVideoUsesOgImage(): void
    {
        $html = '<head><title>T</title><meta property="og:image" content="/og.jpg"></head>'
            . '<iframe src="https://player.vimeo.com/video/123456"></iframe>';

        $videos = VideoSitemapWriter::extractVideos($html, 'https://example.com/v');

        $this->assertSame('https://example.com/og.jpg', $videos[0]['thumbnail']);
    }

    public function testExtractNewsMetadataFromJsonLdGraph(): void
    {
        $html = '<head><meta property="og:site_name" content="Daily"><meta name="news_keywords" content="a, b">'
            . '<script type="application/ld+json">{"@graph":[{"@type":"WebSite"},'
            . '{"@type":"NewsArticle","headline":"Big news","datePublished":"2026-11-20T10:00:00+03:30"}]}</script>'
            . '</head>';

        $meta = NewsSitemapWriter::extractNewsMetadata($html);

        $this->assertSame('2026-11-20T10:00:00+03:30', $meta['publication_date']);
        $this->assertSame('Big news', $meta['title']);
        $this->assertSame('Daily', $meta['publication_name']);
        $this->assertSame('a, b', $meta['keywords']);
    }

    public function testNewsLanguageCode(): void
    {
        $this->assertSame('fa', NewsSitemapWriter::languageCode('fa-IR'));
        $this->assertSame('zh-cn', NewsSitemapWriter::languageCode('zh_CN'));
        $this->assertSame('en', NewsSitemapWriter::languageCode(null));
    }

    public function testBrokenLinksAcceptCrawlerAndDatabaseKeys(): void
    {
        $checker = new ContentQualityChecker();

        $broken = $checker->findBrokenLinks([
            ['url' => 'https://example.com/a', 'status' => 404],
            ['url' => 'https://example.com/b', 'status_code' => 500],
            ['url' => 'https://example.com/c', 'status' => 200],
        ]);

        $this->assertSame(['https://example.com/a', 'https://example.com/b'], array_column($broken, 'url'));
    }

    public function testSeoAnalyzerCountsNonLatinWordsAndAcceptsPsr7Headers(): void
    {
        $html = '<html><head><title>صفحه</title></head><body><h1>عنوان</h1><p>'
            . str_repeat('این یک متن فارسی است ', 80) . '</p><a href="/x">x</a></body></html>';

        $result = (new SeoAnalyzer())->analyze('https://example.ir/', $html, 200, ['Content-Encoding' => ['gzip']]);
        $messages = array_merge(
            array_column($result['warnings'], 'message'),
            array_column($result['recommendations'], 'message')
        );

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('Low word count', $message);
            $this->assertStringNotContainsString('No gzip compression', $message);
            $this->assertStringNotContainsString('No internal links', $message);
        }
    }
}
