<?php

namespace IProDev\Sitemap;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HeadlessBrowser
{
    private string $chromePath;
    private int $port;
    private ?int $pid = null;
    private LoggerInterface $logger;
    private bool $waitForAjax;
    private int $ajaxTimeout;

    public function __construct(
        string $chromePath = '/usr/bin/chromium',
        int $port = 9222,
        bool $waitForAjax = true,
        int $ajaxTimeout = 5000,
        ?LoggerInterface $logger = null
    ) {
        $this->chromePath = $chromePath;
        $this->port = $port;
        $this->waitForAjax = $waitForAjax;
        $this->ajaxTimeout = $ajaxTimeout;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Start headless Chrome
     */
    public function start(): void
    {
        if ($this->isRunning()) {
            $this->logger->info("Chrome already running on port {$this->port}");
            return;
        }

        $flags = '--headless --disable-gpu --remote-debugging-port=%d';
        $flags .= ' --no-sandbox --disable-setuid-sandbox --disable-dev-shm-usage';
        $command = sprintf(
            '%s ' . $flags . ' > /dev/null 2>&1 & echo $!',
            escapeshellarg($this->chromePath),
            $this->port
        );

        $this->logger->info("Starting headless Chrome", ['port' => $this->port]);

        $output = shell_exec($command);
        $this->pid = (int)trim($output);

        // Wait for Chrome to start
        sleep(2);

        if (!$this->isRunning()) {
            throw new \RuntimeException("Failed to start Chrome");
        }

        $this->logger->info("Chrome started", ['pid' => $this->pid]);
    }

    /**
     * Stop Chrome
     */
    public function stop(): void
    {
        if (!$this->isRunning()) {
            return;
        }

        if ($this->pid) {
            $this->logger->info("Stopping Chrome", ['pid' => $this->pid]);
            posix_kill($this->pid, SIGTERM);
            sleep(1);

            if ($this->isRunning()) {
                posix_kill($this->pid, SIGKILL);
            }

            $this->pid = null;
        }
    }

    /**
     * Render page with JavaScript
     */
    public function render(string $url, array $options = []): array
    {
        if (!$this->isRunning()) {
            $this->start();
        }

        try {
            $client = new \GuzzleHttp\Client();

            // Create new page
            $response = $client->get("http://localhost:{$this->port}/json/new");
            $page = json_decode($response->getBody(), true);
            $wsUrl = $page['webSocketDebuggerUrl'];

            // Connect via WebSocket would be ideal, but for simplicity we'll use HTTP
            // Navigate to URL
            $this->navigate($page['id'], $url);

            // Wait for page load
            sleep($options['wait'] ?? 2);

            // Wait for AJAX if enabled
            if ($this->waitForAjax) {
                $this->waitForNetworkIdle($page['id'], $this->ajaxTimeout);
            }

            // Get rendered HTML
            $html = $this->getHtml($page['id']);

            // Take screenshot if requested
            $screenshot = null;
            if ($options['screenshot'] ?? false) {
                $screenshot = $this->takeScreenshot($page['id']);
            }

            // Close page
            $client->get("http://localhost:{$this->port}/json/close/{$page['id']}");

            return [
                'html' => $html,
                'screenshot' => $screenshot,
                'url' => $url
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Failed to render page", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to render: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Navigate to URL
     */
    private function navigate(string $pageId, string $url): void
    {
        $client = new \GuzzleHttp\Client();

        $client->post("http://localhost:{$this->port}/json/page/{$pageId}", [
            'json' => [
                'method' => 'Page.navigate',
                'params' => ['url' => $url]
            ]
        ]);
    }

    /**
     * Wait for network idle (AJAX completion)
     */
    private function waitForNetworkIdle(string $pageId, int $timeout): void
    {
        $start = microtime(true);
        $maxWait = $timeout / 1000;

        // Simple implementation: just wait
        // In production, would monitor network activity via DevTools Protocol
        sleep(min(2, $maxWait));
    }

    /**
     * Get rendered HTML
     */
    private function getHtml(string $pageId): string
    {
        $client = new \GuzzleHttp\Client();

        $response = $client->post("http://localhost:{$this->port}/json/page/{$pageId}", [
            'json' => [
                'method' => 'Runtime.evaluate',
                'params' => [
                    'expression' => 'document.documentElement.outerHTML'
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);
        return $result['result']['value'] ?? '';
    }

    /**
     * Take screenshot
     */
    private function takeScreenshot(string $pageId): ?string
    {
        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->post("http://localhost:{$this->port}/json/page/{$pageId}", [
                'json' => [
                    'method' => 'Page.captureScreenshot',
                    'params' => [
                        'format' => 'png'
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['result']['data'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to take screenshot", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if Chrome is running
     */
    private function isRunning(): bool
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 2]);
            $response = $client->get("http://localhost:{$this->port}/json/version");
            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get Chrome version
     */
    public function getVersion(): ?string
    {
        if (!$this->isRunning()) {
            return null;
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get("http://localhost:{$this->port}/json/version");
            $data = json_decode($response->getBody(), true);
            return $data['Browser'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
