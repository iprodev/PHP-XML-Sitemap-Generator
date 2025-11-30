<?php

namespace IProDev\Sitemap;

class RateLimiter
{
    private int $maxRequests;
    private int $timeWindow; // in seconds
    private array $requests = [];
    private int $delayMs;
    private bool $respectRetryAfter;

    public function __construct(
        int $maxRequests = 100,
        int $timeWindow = 60,
        int $delayMs = 0,
        bool $respectRetryAfter = true
    ) {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->delayMs = $delayMs;
        $this->respectRetryAfter = $respectRetryAfter;
    }

    /**
     * Wait if necessary before making a request
     */
    public function throttle(string $domain = 'default'): void
    {
        $this->cleanOldRequests($domain);
        
        // Check if we've hit the limit
        if ($this->isLimitReached($domain)) {
            $waitTime = $this->calculateWaitTime($domain);
            if ($waitTime > 0) {
                usleep($waitTime * 1000); // Convert to microseconds
                $this->cleanOldRequests($domain);
            }
        }
        
        // Add delay between requests if configured
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
        
        $this->recordRequest($domain);
    }

    /**
     * Check if we should respect Retry-After header
     */
    public function handleRetryAfter(int $retryAfter): void
    {
        if ($this->respectRetryAfter && $retryAfter > 0) {
            sleep($retryAfter);
        }
    }

    /**
     * Record a request
     */
    private function recordRequest(string $domain): void
    {
        if (!isset($this->requests[$domain])) {
            $this->requests[$domain] = [];
        }
        
        $this->requests[$domain][] = microtime(true);
    }

    /**
     * Clean old requests outside time window
     */
    private function cleanOldRequests(string $domain): void
    {
        if (!isset($this->requests[$domain])) {
            return;
        }
        
        $now = microtime(true);
        $cutoff = $now - $this->timeWindow;
        
        $this->requests[$domain] = array_filter(
            $this->requests[$domain],
            fn($time) => $time > $cutoff
        );
    }

    /**
     * Check if rate limit is reached
     */
    private function isLimitReached(string $domain): bool
    {
        if (!isset($this->requests[$domain])) {
            return false;
        }
        
        return count($this->requests[$domain]) >= $this->maxRequests;
    }

    /**
     * Calculate how long to wait (in milliseconds)
     */
    private function calculateWaitTime(string $domain): int
    {
        if (!isset($this->requests[$domain]) || empty($this->requests[$domain])) {
            return 0;
        }
        
        $oldestRequest = min($this->requests[$domain]);
        $now = microtime(true);
        $elapsed = $now - $oldestRequest;
        
        if ($elapsed >= $this->timeWindow) {
            return 0;
        }
        
        return (int)(($this->timeWindow - $elapsed) * 1000);
    }

    /**
     * Get current request count for domain
     */
    public function getRequestCount(string $domain = 'default'): int
    {
        $this->cleanOldRequests($domain);
        return isset($this->requests[$domain]) ? count($this->requests[$domain]) : 0;
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        $stats = [];
        
        foreach ($this->requests as $domain => $requests) {
            $this->cleanOldRequests($domain);
            $stats[$domain] = [
                'current_requests' => count($requests),
                'max_requests' => $this->maxRequests,
                'time_window' => $this->timeWindow,
                'percentage' => round((count($requests) / $this->maxRequests) * 100, 2)
            ];
        }
        
        return $stats;
    }

    /**
     * Reset all counters
     */
    public function reset(string $domain = null): void
    {
        if ($domain) {
            unset($this->requests[$domain]);
        } else {
            $this->requests = [];
        }
    }
}
