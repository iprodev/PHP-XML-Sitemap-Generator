<?php

namespace IProDev\Sitemap;

class CrawlCheckpoint
{
    private string $checkpointFile;
    private int $interval;
    private array $state = [];

    public function __construct(string $checkpointFile = './checkpoint.json', int $interval = 1000)
    {
        $this->checkpointFile = $checkpointFile;
        $this->interval = $interval;
    }

    /**
     * Save checkpoint
     */
    public function save(array $state): void
    {
        $state['saved_at'] = date('Y-m-d H:i:s');
        $state['timestamp'] = time();

        $dir = dirname($this->checkpointFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->checkpointFile,
            json_encode($state, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Load checkpoint
     */
    public function load(): ?array
    {
        if (!file_exists($this->checkpointFile)) {
            return null;
        }

        $content = file_get_contents($this->checkpointFile);
        return json_decode($content, true);
    }

    /**
     * Check if checkpoint exists
     */
    public function exists(): bool
    {
        return file_exists($this->checkpointFile);
    }

    /**
     * Delete checkpoint
     */
    public function delete(): bool
    {
        if (file_exists($this->checkpointFile)) {
            return unlink($this->checkpointFile);
        }
        return false;
    }

    /**
     * Get checkpoint age in seconds
     */
    public function getAge(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        $state = $this->load();
        if (!$state || !isset($state['timestamp'])) {
            return null;
        }

        return time() - $state['timestamp'];
    }

    /**
     * Check if checkpoint is stale (older than 24 hours)
     */
    public function isStale(int $maxAge = 86400): bool
    {
        $age = $this->getAge();
        return $age !== null && $age > $maxAge;
    }

    /**
     * Get checkpoint info
     */
    public function getInfo(): ?array
    {
        if (!$this->exists()) {
            return null;
        }

        $state = $this->load();
        if (!$state) {
            return null;
        }

        return [
            'file' => $this->checkpointFile,
            'saved_at' => $state['saved_at'] ?? null,
            'age_seconds' => $this->getAge(),
            'pages_crawled' => $state['pages_crawled'] ?? 0,
            'urls_queued' => count($state['queue'] ?? []),
            'is_stale' => $this->isStale()
        ];
    }
}
