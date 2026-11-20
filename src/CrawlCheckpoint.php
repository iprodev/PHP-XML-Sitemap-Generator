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

        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode checkpoint: ' . json_last_error_msg());
        }

        // Write to a temporary file and rename it, so an interrupted save never leaves a corrupt checkpoint
        $tmp = $this->checkpointFile . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $this->checkpointFile)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write checkpoint file: {$this->checkpointFile}");
        }
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
        if ($content === false) {
            return null;
        }

        $state = json_decode($content, true);
        return is_array($state) ? $state : null;
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
     * Number of processed pages between two saves
     */
    public function getInterval(): int
    {
        return $this->interval;
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
