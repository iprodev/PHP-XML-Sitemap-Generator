<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\ProxyManager;
use PHPUnit\Framework\TestCase;

class ProxyManagerTest extends TestCase
{
    public function testCredentialsAreEmbeddedInProxyUrlNotSentToTargetSites(): void
    {
        $manager = new ProxyManager();
        $manager->addProxy('http://proxy.example.com:8080', 'user:p@ss:word');

        $config = $manager->getGuzzleProxyConfig();

        // Guzzle's "auth" option would send an Authorization header to every crawled site
        $this->assertArrayNotHasKey('auth', $config);
        $this->assertSame('http://user:p%40ss%3Aword@proxy.example.com:8080', $config['proxy']);
    }

    public function testProxyWithoutCredentials(): void
    {
        $manager = new ProxyManager(['socks5://proxy.example.com:1080']);

        $this->assertSame(['proxy' => 'socks5://proxy.example.com:1080'], $manager->getGuzzleProxyConfig());
    }

    public function testBuildProxyUrl(): void
    {
        $this->assertSame('http://proxy.example.com:3128', ProxyManager::buildProxyUrl('proxy.example.com:3128'));
        $this->assertSame(
            'http://new:pw@p.example.com:1',
            ProxyManager::buildProxyUrl('http://old:old@p.example.com:1', 'new:pw')
        );
        $this->assertSame('http://user@p.example.com:1', ProxyManager::buildProxyUrl('http://p.example.com:1', 'user'));
    }

    public function testNoProxyConfiguredReturnsNull(): void
    {
        $this->assertNull((new ProxyManager())->getGuzzleProxyConfig());
    }

    public function testLoadFromFileSkipsBlankLinesAndKeepsRotation(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'proxies');
        file_put_contents($file, "# comment\nhttp://p1.example.com:8080\n   \nhttp://p2.example.com:8080|u:p\n");

        try {
            $manager = ProxyManager::loadFromFile($file, true);

            $this->assertSame(2, $manager->count());
            $this->assertSame('http://p2.example.com:8080', $manager->getNextProxy()['url']);
        } finally {
            unlink($file);
        }
    }
}
