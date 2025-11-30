<?php

namespace IProDev\Sitemap\Cache;

interface CacheInterface
{
    /**
     * Get item from cache
     */
    public function get(string $key): mixed;

    /**
     * Set item in cache
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Check if key exists
     */
    public function has(string $key): bool;

    /**
     * Delete item from cache
     */
    public function delete(string $key): bool;

    /**
     * Clear all cache
     */
    public function clear(): bool;

    /**
     * Get multiple items
     */
    public function getMultiple(array $keys): array;

    /**
     * Set multiple items
     */
    public function setMultiple(array $items, int $ttl = 3600): bool;
}
