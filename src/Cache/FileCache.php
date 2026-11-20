<?php

namespace IProDev\Sitemap\Cache;

/**
 * File based cache.
 *
 * Entries are stored as JSON instead of PHP serialization: reading a cache
 * file must never be able to instantiate PHP objects, even if someone else
 * can write to the cache directory.
 */
class FileCache implements CacheInterface
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(string $cacheDir = './cache', int $defaultTtl = 3600)
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = $this->readEntry($file);

        if ($data === null || $data['expires'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getFilePath($key);

        $data = [
            'key' => $key,
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $e) {
            // Value is not JSON-serializable (e.g. an object or a resource)
            return false;
        }

        return file_put_contents($file, $encoded, LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache') ?: [];

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function getMultiple(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function setMultiple(array $items, ?int $ttl = null): bool
    {
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clean expired (and corrupt) cache entries
     */
    public function cleanup(): int
    {
        $files = glob($this->cacheDir . '/*.cache') ?: [];
        $deleted = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $data = $this->readEntry($file);

            if ($data === null || $data['expires'] < time()) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/*.cache') ?: [];
        $totalSize = 0;
        $expired = 0;
        $valid = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $totalSize += filesize($file);
            $data = $this->readEntry($file);

            if ($data === null || $data['expires'] < time()) {
                $expired++;
            } else {
                $valid++;
            }
        }

        return [
            'total_entries' => count($files),
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'total_size' => $totalSize,
            'cache_dir' => $this->cacheDir
        ];
    }

    /**
     * Read and validate a cache entry.
     *
     * @return array{value: mixed, expires: int}|null null if the entry is missing or corrupt
     */
    private function readEntry(string $file): ?array
    {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        if (!is_array($data) || !array_key_exists('value', $data) || !is_int($data['expires'] ?? null)) {
            return null;
        }

        return $data;
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}
