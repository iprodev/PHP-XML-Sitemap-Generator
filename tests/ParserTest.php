<?php

namespace IProDev\Sitemap\Tests;

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\Parser;

class ParserTest extends TestCase
{
    public function testExtractLinks(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body>
    <a href="https://example.com/page1">Page 1</a>
    <a href="/page2">Page 2</a>
    <a href="page3">Page 3</a>
    <a href="#fragment">Fragment</a>
    <a href="mailto:test@example.com">Email</a>
    <a href="javascript:void(0)">JS</a>
</body>
</html>
HTML;

        $baseUrl = 'https://example.com/';
        $links = Parser::extractLinks($html, $baseUrl);

        $this->assertIsArray($links);
        $this->assertContains('https://example.com/page1', $links);
        $this->assertContains('https://example.com/page2', $links);
        $this->assertContains('https://example.com/page3', $links);
        $this->assertNotContains('mailto:test@example.com', $links);
        $this->assertNotContains('javascript:void(0)', $links);
    }

    public function testExtractLinksWithEmptyHtml(): void
    {
        $links = Parser::extractLinks('', 'https://example.com');
        $this->assertIsArray($links);
        $this->assertEmpty($links);
    }

    public function testResolveUrl(): void
    {
        $baseUrl = 'https://example.com/path/';
        
        // Absolute URL
        $this->assertEquals(
            'https://example.com/page',
            Parser::resolveUrl('https://example.com/page', $baseUrl)
        );

        // Relative URL
        $this->assertEquals(
            'https://example.com/path/page',
            Parser::resolveUrl('page', $baseUrl)
        );

        // Root-relative URL
        $this->assertEquals(
            'https://example.com/page',
            Parser::resolveUrl('/page', $baseUrl)
        );

        // Fragment only
        $this->assertNull(Parser::resolveUrl('#fragment', $baseUrl));

        // Non-HTTP scheme
        $this->assertNull(Parser::resolveUrl('mailto:test@example.com', $baseUrl));
    }

    public function testGetCanonical(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <link rel="canonical" href="https://example.com/canonical-page" />
</head>
<body>Content</body>
</html>
HTML;

        $baseUrl = 'https://example.com/original-page';
        $canonical = Parser::getCanonical($html, $baseUrl);

        $this->assertEquals('https://example.com/canonical-page', $canonical);
    }

    public function testGetCanonicalWithRelativeUrl(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <link rel="canonical" href="/canonical-page" />
</head>
<body>Content</body>
</html>
HTML;

        $baseUrl = 'https://example.com/original-page';
        $canonical = Parser::getCanonical($html, $baseUrl);

        $this->assertEquals('https://example.com/canonical-page', $canonical);
    }

    public function testGetCanonicalNotFound(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><title>No Canonical</title></head>
<body>Content</body>
</html>
HTML;

        $baseUrl = 'https://example.com/page';
        $canonical = Parser::getCanonical($html, $baseUrl);

        $this->assertNull($canonical);
    }

    public function testGetMetaRobots(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta name="robots" content="noindex, nofollow" />
</head>
<body>Content</body>
</html>
HTML;

        $directives = Parser::getMetaRobots($html);

        $this->assertIsArray($directives);
        $this->assertContains('noindex', $directives);
        $this->assertContains('nofollow', $directives);
    }

    public function testUrlDeduplication(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <a href="https://example.com/page">Page 1</a>
    <a href="https://example.com/page">Page 2</a>
    <a href="https://example.com/page">Page 3</a>
</body>
</html>
HTML;

        $baseUrl = 'https://example.com/';
        $links = Parser::extractLinks($html, $baseUrl);

        $this->assertCount(1, $links);
        $this->assertEquals('https://example.com/page', $links[0]);
    }

    public function testMalformedHtmlHandling(): void
    {
        $malformedHtml = '<html><body><a href="/page">Link</a><div><a href="/page2">';
        
        $baseUrl = 'https://example.com/';
        $links = Parser::extractLinks($malformedHtml, $baseUrl);

        $this->assertIsArray($links);
        $this->assertNotEmpty($links);
    }

    public function testInvalidUrlInResolve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Parser::resolveUrl('ht!tp://invalid', 'https://example.com');
    }
}
