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

    public function testParseDocument(): void
    {
        $html = '<html lang="fa"><head><base href="/blog/"><title> سلام   دنیا </title>'
            . '<link rel="alternate canonical" href="post?id=1">'
            . '<meta name="robots" content="NoIndex, follow"><meta name="description" content="توضیح"></head>'
            . '<body><h1>عنوان</h1><p>این متن می‌شود شمرد</p><script>var a = "not words";</script>'
            . '<a href="a">a</a> <a rel="external NoFollow" href="/skip">s</a> <a href="mailto:x@example.com">m</a>'
            . '<img src="x.png"></body></html>';

        $doc = Parser::parseDocument($html, 'https://example.com/x/y');

        $this->assertSame(['https://example.com/blog/a'], $doc['links']);
        $this->assertSame('https://example.com/blog/post?id=1', $doc['canonical']);
        $this->assertSame(['noindex', 'follow'], $doc['robots']);
        $this->assertSame('سلام دنیا', $doc['title']);
        $this->assertSame('توضیح', $doc['meta_description']);
        $this->assertSame('عنوان', $doc['h1']);
        $this->assertSame('fa', $doc['lang']);
        $this->assertSame(1, $doc['image_count']);
        $this->assertSame(3, $doc['link_count']);
        // عنوان، این، متن، می‌شود، شمرد، a، s، m (script content is ignored)
        $this->assertSame(8, $doc['word_count']);
    }

    public function testParseDocumentWithEmptyHtml(): void
    {
        $doc = Parser::parseDocument('', 'https://example.com/');

        $this->assertSame([], $doc['links']);
        $this->assertNull($doc['canonical']);
        $this->assertSame(0, $doc['word_count']);
    }

    public function testNonAsciiLinksSurviveParsing(): void
    {
        $links = Parser::extractLinks('<a href="/مقاله/یک">x</a>', 'https://example.com/');

        $this->assertSame(['https://example.com/%D9%85%D9%82%D8%A7%D9%84%D9%87/%DB%8C%DA%A9'], $links);
    }

    public function testToUtf8(): void
    {
        $latin1 = "<html><body>caf\xe9</body></html>";

        $this->assertSame(
            '<html><body>café</body></html>',
            Parser::toUtf8($latin1, 'text/html; charset=windows-1252')
        );
        $this->assertSame(
            '<meta charset="iso-8859-1">café',
            Parser::toUtf8("<meta charset=\"iso-8859-1\">caf\xe9")
        );
        $this->assertSame('café', Parser::toUtf8('café', 'text/html; charset=UTF-8'));
        $this->assertSame('plain', Parser::toUtf8('plain', 'text/html; charset=unknown-charset'));
    }
}
