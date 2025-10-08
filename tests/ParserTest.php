<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\Parser;

final class ParserTest extends TestCase {
    public function testExtractLinks(): void {
        $html = '<html><body><a href="/a">A</a><a href="http://example.com/b">B</a><a href="#frag">F</a><a href="mailto:x@y">M</a></body></html>';
        $base = 'http://example.com';
        $links = Parser::extractLinks($html, $base);
        $this->assertContains('http://example.com/a', $links);
        $this->assertContains('http://example.com/b', $links);
        $this->assertNotContains('mailto:x@y', $links);
    }

    public function testCanonical(): void {
        $html = '<html><head><link rel="canonical" href="/c"/></head></html>';
        $base = 'http://example.com';
        $can = Parser::getCanonical($html, $base);
        $this->assertSame('http://example.com/c', $can);
    }
}
