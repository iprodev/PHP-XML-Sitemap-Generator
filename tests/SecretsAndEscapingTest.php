<?php

namespace IProDev\Sitemap\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use IProDev\Sitemap\ChangeDetector;
use IProDev\Sitemap\Database\Database;
use IProDev\Sitemap\WebhookNotifier;
use PHPUnit\Framework\TestCase;

class SecretsAndEscapingTest extends TestCase
{
    private const CONFIG = [
        'url' => 'https://example.com',
        'proxy' => 'http://bob:s3cret@proxy.example.com:8080',
        'dbDsn' => 'pgsql:host=db;dbname=sitemap;password=hunter2',
        'webhookUrl' => 'https://hooks.example.com/services/XYZTOKEN',
    ];

    private const SECRETS = ['s3cret', 'hunter2', 'XYZTOKEN'];

    public function testHtmlChangeReportEscapesCrawledUrls(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('getNewUrls')->willReturn([['url' => 'https://example.com/?q=<script>alert(1)</script>']]);
        $db->method('getChangedUrls')->willReturn([['url' => "https://example.com/'onmouseover='alert(1)"]]);
        $db->method('getDeletedUrls')->willReturn([]);

        $html = (new ChangeDetector($db))->generateReport(1, 2, 'html');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString("'onmouseover='", $html);
    }

    public function testCrawlStartedWebhookDoesNotLeakCredentials(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200)]));
        $stack->push(Middleware::history($history));

        $notifier = new WebhookNotifier();
        $property = new \ReflectionProperty(WebhookNotifier::class, 'client');
        $property->setAccessible(true);
        $property->setValue($notifier, new Client(['handler' => $stack]));

        $notifier->addWebhook('https://hooks.example.com/receive');
        $notifier->notifyCrawlStarted('https://example.com', self::CONFIG);

        $this->assertCount(1, $history);
        $body = (string)$history[0]['request']->getBody();
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $this->assertStringContainsString('proxy.example.com', $body);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCrawlConfigIsStoredWithoutCredentials(): void
    {
        $db = new Database('sqlite::memory:');
        $db->createTables();
        $db->startCrawl('example.com', 'https://example.com', self::CONFIG);

        $stored = (string)$db->getPdo()->query('SELECT config FROM sitemap_crawls')->fetchColumn();

        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $this->assertStringContainsString('proxy.example.com', $stored);
    }
}
