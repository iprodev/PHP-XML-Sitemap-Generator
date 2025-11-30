<?php

namespace IProDev\Sitemap\Tests;

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\Utils;

class UtilsTest extends TestCase
{
    public function testNormalizeUrl(): void
    {
        $this->assertEquals('https://example.com', Utils::normalizeUrl('https://example.com/'));
        $this->assertEquals('https://example.com/path', Utils::normalizeUrl('https://example.com/path/'));
        $this->assertEquals('https://example.com', Utils::normalizeUrl('https://example.com'));
    }

    public function testFormatBytes(): void
    {
        $this->assertEquals('1 KB', Utils::formatBytes(1024));
        $this->assertEquals('1 MB', Utils::formatBytes(1024 * 1024));
        $this->assertEquals('1.5 KB', Utils::formatBytes(1536));
        $this->assertEquals('500 B', Utils::formatBytes(500));
    }

    public function testFormatDuration(): void
    {
        $this->assertEquals('30s', Utils::formatDuration(30));
        $this->assertEquals('1m 30s', Utils::formatDuration(90));
        $this->assertEquals('2m 15s', Utils::formatDuration(135));
    }

    public function testIsValidUrl(): void
    {
        $this->assertTrue(Utils::isValidUrl('https://example.com'));
        $this->assertTrue(Utils::isValidUrl('http://example.com/path'));
        $this->assertFalse(Utils::isValidUrl('not-a-url'));
        $this->assertFalse(Utils::isValidUrl('ftp://example.com'));
        $this->assertFalse(Utils::isValidUrl(''));
    }

    public function testGetDomain(): void
    {
        $this->assertEquals('example.com', Utils::getDomain('https://example.com/path'));
        $this->assertEquals('sub.example.com', Utils::getDomain('https://sub.example.com'));
        $this->assertNull(Utils::getDomain('not-a-url'));
    }

    public function testCalculateProgress(): void
    {
        $this->assertEquals(50.0, Utils::calculateProgress(50, 100));
        $this->assertEquals(100.0, Utils::calculateProgress(100, 100));
        $this->assertEquals(0.0, Utils::calculateProgress(0, 100));
        $this->assertEquals(0.0, Utils::calculateProgress(10, 0));
    }

    public function testProgressBar(): void
    {
        $bar = Utils::progressBar(50, 100, 10);
        $this->assertStringContainsString('[', $bar);
        $this->assertStringContainsString(']', $bar);
        $this->assertStringContainsString('50/100', $bar);
        $this->assertStringContainsString('50.0%', $bar);
    }

    public function testCleanUrl(): void
    {
        $this->assertEquals(
            'https://example.com/path',
            Utils::cleanUrl('https://example.com/path?query=1#fragment')
        );

        $this->assertEquals(
            'https://example.com/path',
            Utils::cleanUrl('https://example.com/path?query=1', true)
        );

        $this->assertEquals(
            'https://example.com/path?query=1',
            Utils::cleanUrl('https://example.com/path?query=1', false)
        );
    }

    public function testGetMemoryUsage(): void
    {
        $usage = Utils::getMemoryUsage();
        $this->assertIsString($usage);
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s[KMGT]?B/', $usage);
    }

    public function testGetPeakMemoryUsage(): void
    {
        $usage = Utils::getPeakMemoryUsage();
        $this->assertIsString($usage);
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s[KMGT]?B/', $usage);
    }
}
