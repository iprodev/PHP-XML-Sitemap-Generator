<?php

namespace IProDev\Sitemap;

class ProxyManager
{
    private array $proxies = [];
    private int $currentIndex = 0;
    private array $stats = [];
    private bool $rotateProxies = false;

    public function __construct(array $proxies = [], bool $rotateProxies = false)
    {
        $this->setProxies($proxies);
        $this->rotateProxies = $rotateProxies;
    }

    /**
     * Set proxies
     */
    public function setProxies(array $proxies): void
    {
        $this->proxies = [];
        
        foreach ($proxies as $proxy) {
            if (is_string($proxy)) {
                $this->proxies[] = ['url' => $proxy, 'auth' => null];
            } elseif (is_array($proxy)) {
                $this->proxies[] = [
                    'url' => $proxy['url'] ?? $proxy[0],
                    'auth' => $proxy['auth'] ?? null
                ];
            }
        }

        $this->initStats();
    }

    /**
     * Add single proxy
     */
    public function addProxy(string $url, ?string $auth = null): void
    {
        $this->proxies[] = ['url' => $url, 'auth' => $auth];
        $this->initStats();
    }

    /**
     * Get current proxy
     */
    public function getProxy(): ?array
    {
        if (empty($this->proxies)) {
            return null;
        }

        return $this->proxies[$this->currentIndex];
    }

    /**
     * Get next proxy (for rotation)
     */
    public function getNextProxy(): ?array
    {
        if (empty($this->proxies)) {
            return null;
        }

        if ($this->rotateProxies) {
            $this->currentIndex = ($this->currentIndex + 1) % count($this->proxies);
        }

        return $this->getProxy();
    }

    /**
     * Get Guzzle proxy configuration
     */
    public function getGuzzleProxyConfig(): ?array
    {
        $proxy = $this->getProxy();
        
        if (!$proxy) {
            return null;
        }

        $config = ['proxy' => $proxy['url']];

        if ($proxy['auth']) {
            $config['auth'] = explode(':', $proxy['auth'], 2);
        }

        return $config;
    }

    /**
     * Mark proxy as failed
     */
    public function markFailed(string $proxyUrl): void
    {
        if (isset($this->stats[$proxyUrl])) {
            $this->stats[$proxyUrl]['failures']++;
            $this->stats[$proxyUrl]['last_failed'] = time();
        }
    }

    /**
     * Mark proxy as successful
     */
    public function markSuccess(string $proxyUrl): void
    {
        if (isset($this->stats[$proxyUrl])) {
            $this->stats[$proxyUrl]['successes']++;
            $this->stats[$proxyUrl]['last_success'] = time();
        }
    }

    /**
     * Get best performing proxy
     */
    public function getBestProxy(): ?array
    {
        if (empty($this->proxies)) {
            return null;
        }

        $best = null;
        $bestScore = -1;

        foreach ($this->proxies as $proxy) {
            $stats = $this->stats[$proxy['url']];
            $total = $stats['successes'] + $stats['failures'];
            
            if ($total === 0) {
                continue;
            }

            $score = $stats['successes'] / $total;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $proxy;
            }
        }

        return $best ?? $this->proxies[0];
    }

    /**
     * Remove failed proxies
     */
    public function removeFailedProxies(float $threshold = 0.5): int
    {
        $removed = 0;
        $keep = [];

        foreach ($this->proxies as $proxy) {
            $stats = $this->stats[$proxy['url']];
            $total = $stats['successes'] + $stats['failures'];
            
            if ($total === 0) {
                $keep[] = $proxy;
                continue;
            }

            $successRate = $stats['successes'] / $total;

            if ($successRate >= $threshold) {
                $keep[] = $proxy;
            } else {
                $removed++;
            }
        }

        $this->proxies = $keep;
        $this->currentIndex = 0;

        return $removed;
    }

    /**
     * Get proxy statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get proxy count
     */
    public function count(): int
    {
        return count($this->proxies);
    }

    /**
     * Test proxy connection
     */
    public function testProxy(string $proxyUrl, string $testUrl = 'https://www.google.com'): array
    {
        $client = new \GuzzleHttp\Client();
        
        try {
            $start = microtime(true);
            
            $response = $client->get($testUrl, [
                'proxy' => $proxyUrl,
                'timeout' => 10,
                'allow_redirects' => true
            ]);
            
            $duration = microtime(true) - $start;

            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'duration' => round($duration, 3),
                'message' => 'Proxy is working'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test all proxies
     */
    public function testAllProxies(string $testUrl = 'https://www.google.com'): array
    {
        $results = [];

        foreach ($this->proxies as $proxy) {
            $results[$proxy['url']] = $this->testProxy($proxy['url'], $testUrl);
        }

        return $results;
    }

    /**
     * Initialize statistics
     */
    private function initStats(): void
    {
        foreach ($this->proxies as $proxy) {
            if (!isset($this->stats[$proxy['url']])) {
                $this->stats[$proxy['url']] = [
                    'successes' => 0,
                    'failures' => 0,
                    'last_success' => null,
                    'last_failed' => null
                ];
            }
        }
    }

    /**
     * Load proxies from file
     */
    public static function loadFromFile(string $filename): self
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("Proxy file not found: {$filename}");
        }

        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $proxies = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }

            // Format: proxy_url or proxy_url|username:password
            if (strpos($line, '|') !== false) {
                list($url, $auth) = explode('|', $line, 2);
                $proxies[] = ['url' => trim($url), 'auth' => trim($auth)];
            } else {
                $proxies[] = ['url' => $line, 'auth' => null];
            }
        }

        return new self($proxies);
    }

    /**
     * Save proxies to file
     */
    public function saveToFile(string $filename): void
    {
        $lines = [];

        foreach ($this->proxies as $proxy) {
            if ($proxy['auth']) {
                $lines[] = $proxy['url'] . '|' . $proxy['auth'];
            } else {
                $lines[] = $proxy['url'];
            }
        }

        file_put_contents($filename, implode("\n", $lines));
    }
}
