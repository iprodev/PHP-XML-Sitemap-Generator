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

    public function testRedactUrl(): void
    {
        $this->assertSame(
            'http://***@proxy.example.com:8080',
            Utils::redactUrl('http://user:p@ss@proxy.example.com:8080')
        );
        $this->assertSame('https://example.com/a@b', Utils::redactUrl('https://example.com/a@b'));
        $this->assertSame('https://example.com/', Utils::redactUrl('https://example.com/'));
        $this->assertSame('./proxies.txt', Utils::redactUrl('./proxies.txt'));
    }

    public function testRedactConfigMasksCredentials(): void
    {
        $config = [
            'url' => 'https://example.com/page?x=1',
            'proxy' => 'http://bob:s3cret@proxy.example.com:8080',
            'proxyFile' => './proxies.txt',
            'dbDsn' => 'pgsql:host=db;dbname=sitemap;user=app;password=hunter2',
            'otherDsn' => 'mysql://app:hunter3@db/sitemap',
            'webhookUrl' => 'https://hooks.example.com/services/T000/B000/XYZTOKEN',
            'redisPassword' => 'r3dis',
            'nested' => ['apiKey' => 'k3y', 'auth' => ['token' => 't0ken']],
            'concurrency' => 10,
            'verbose' => true,
            'exclude' => ['/admin/*'],
        ];

        $redacted = Utils::redactConfig($config);
        $json = (string)json_encode($redacted);

        foreach (['s3cret', 'hunter2', 'hunter3', 'XYZTOKEN', 'r3dis', 'k3y', 't0ken'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }

        $this->assertSame('https://example.com/page?x=1', $redacted['url']);
        $this->assertSame('http://***@proxy.example.com:8080', $redacted['proxy']);
        $this->assertSame('./proxies.txt', $redacted['proxyFile']);
        $this->assertSame('pgsql:host=db;dbname=sitemap;user=app;password=***', $redacted['dbDsn']);
        $this->assertSame('https://hooks.example.com/[REDACTED]', $redacted['webhookUrl']);
        $this->assertSame(10, $redacted['concurrency']);
        $this->assertTrue($redacted['verbose']);
        $this->assertSame(['/admin/*'], $redacted['exclude']);
    }

    public function testCountWords(): void
    {
        $this->assertSame(3, Utils::countWords('Hello, wide world!'));
        $this->assertSame(5, Utils::countWords('این متن را می‌شود شمرد'));
        $this->assertSame(2, Utils::countWords("it's 2026"));
        $this->assertSame(0, Utils::countWords('  ... '));
    }
}
