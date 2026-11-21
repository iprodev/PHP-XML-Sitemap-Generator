<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\UrlNormalizer;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    /**
     * @dataProvider urlProvider
     */
    public function testNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, UrlNormalizer::normalize($input));
    }

    public static function urlProvider(): array
    {
        return [
            'lowercase scheme and host' => ['HTTPS://Example.COM/Path', 'https://example.com/Path'],
            'empty path' => ['https://example.com', 'https://example.com/'],
            'default https port' => ['https://example.com:443/a', 'https://example.com/a'],
            'default http port' => ['http://example.com:80/a', 'http://example.com/a'],
            'other port kept' => ['http://example.com:8080/a', 'http://example.com:8080/a'],
            'fragment removed' => ['https://example.com/a#section', 'https://example.com/a'],
            'tracking parameters removed' => [
                'https://example.com/a?utm_source=x&id=5&UTM_Medium=y&fbclid=z',
                'https://example.com/a?id=5',
            ],
            'only tracking parameters' => ['https://example.com/a?utm_source=x', 'https://example.com/a'],
            'other parameters keep order' => ['https://example.com/a?b=2&a=1', 'https://example.com/a?b=2&a=1'],
            'trailing slash kept' => ['https://example.com/a/', 'https://example.com/a/'],
            'not a URL' => ['/relative/path', '/relative/path'],
        ];
    }

    public function testHost(): void
    {
        $this->assertSame('example.com', UrlNormalizer::host('https://Example.com/x'));
        $this->assertNull(UrlNormalizer::host('/relative'));
    }
}
