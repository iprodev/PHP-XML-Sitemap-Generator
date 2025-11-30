<?php

namespace IProDev\Sitemap;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class WebhookNotifier
{
    private array $webhooks = [];
    private Client $client;
    private LoggerInterface $logger;

    public function __construct(array $webhooks = [], ?LoggerInterface $logger = null)
    {
        $this->webhooks = $webhooks;
        $this->client = new Client(['timeout' => 10]);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Add webhook URL
     */
    public function addWebhook(string $url, array $events = ['*']): void
    {
        $this->webhooks[] = [
            'url' => $url,
            'events' => $events
        ];
    }

    /**
     * Send notification
     */
    public function notify(string $event, array $data = []): void
    {
        $payload = [
            'event' => $event,
            'timestamp' => date('c'),
            'data' => $data
        ];

        foreach ($this->webhooks as $webhook) {
            // Check if webhook is subscribed to this event
            if (!in_array('*', $webhook['events']) && !in_array($event, $webhook['events'])) {
                continue;
            }

            try {
                $this->sendWebhook($webhook['url'], $payload);
                $this->logger->info("Webhook sent", [
                    'url' => $webhook['url'],
                    'event' => $event
                ]);
            } catch (\Throwable $e) {
                $this->logger->error("Webhook failed", [
                    'url' => $webhook['url'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Notify crawl started
     */
    public function notifyCrawlStarted(string $url, array $config = []): void
    {
        $this->notify('crawl.started', [
            'url' => $url,
            'config' => $config
        ]);
    }

    /**
     * Notify crawl completed
     */
    public function notifyCrawlCompleted(string $url, array $stats = []): void
    {
        $this->notify('crawl.completed', [
            'url' => $url,
            'stats' => $stats
        ]);
    }

    /**
     * Notify crawl failed
     */
    public function notifyCrawlFailed(string $url, string $error): void
    {
        $this->notify('crawl.failed', [
            'url' => $url,
            'error' => $error
        ]);
    }

    /**
     * Notify sitemap generated
     */
    public function notifySitemapGenerated(array $files, array $stats = []): void
    {
        $this->notify('sitemap.generated', [
            'files' => $files,
            'stats' => $stats
        ]);
    }

    /**
     * Notify changes detected
     */
    public function notifyChangesDetected(array $changes): void
    {
        $this->notify('changes.detected', $changes);
    }

    /**
     * Send webhook HTTP request
     */
    private function sendWebhook(string $url, array $payload): void
    {
        $response = $this->client->post($url, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'SitemapGenerator-Webhook/1.0'
            ]
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("Webhook returned status " . $response->getStatusCode());
        }
    }

    /**
     * Test webhook
     */
    public function testWebhook(string $url): array
    {
        try {
            $start = microtime(true);

            $this->sendWebhook($url, [
                'event' => 'test',
                'message' => 'This is a test webhook',
                'timestamp' => date('c')
            ]);

            $duration = microtime(true) - $start;

            return [
                'success' => true,
                'duration' => round($duration, 3),
                'message' => 'Webhook test successful'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
