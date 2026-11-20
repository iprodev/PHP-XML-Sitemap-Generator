<?php

namespace IProDev\Sitemap\Cache;

class RedisCache implements CacheInterface
{
    private ?\Redis $redis = null;
    private string $prefix;
    private int $defaultTtl;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        string $password = null,
        int $database = 0,
        string $prefix = 'sitemap:',
        int $defaultTtl = 3600
    ) {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not loaded');
        }

        $this->redis = new \Redis();
        $this->redis->connect($host, $port);

        if ($password) {
            $this->redis->auth($password);
        }

        $this->redis->select($database);
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false) {
            return null;
        }

        return $this->decode($value);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $encoded = $this->encode($value);
        if ($encoded === null) {
            return false;
        }

        return (bool)$this->redis->setex($this->prefix . $key, $ttl, $encoded);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function clear(): bool
    {
        $pattern = $this->prefix . '*';
        $keys = $this->redis->keys($pattern);

        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    public function getMultiple(array $keys): array
    {
        $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        $values = $this->redis->mGet($prefixedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] !== false ? $this->decode($values[$i]) : null;
        }

        return $result;
    }

    public function setMultiple(array $items, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;

        $encodedItems = [];
        foreach ($items as $key => $value) {
            $encoded = $this->encode($value);
            if ($encoded === null) {
                return false;
            }
            $encodedItems[$key] = $encoded;
        }

        $pipe = $this->redis->multi(\Redis::PIPELINE);

        foreach ($encodedItems as $key => $encoded) {
            $pipe->setex($this->prefix . $key, $ttl, $encoded);
        }

        $results = $pipe->exec();
        return !in_array(false, $results, true);
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $pattern = $this->prefix . '*';
        $keys = $this->redis->keys($pattern);

        return [
            'total_entries' => count($keys),
            'redis_info' => $this->redis->info(),
            'prefix' => $this->prefix
        ];
    }

    /**
     * Values are stored as JSON instead of PHP serialization, so data written
     * to Redis by someone else can never instantiate PHP objects when read.
     */
    private function encode(mixed $value): ?string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $e) {
            return null;
        }
    }

    private function decode(string $raw): mixed
    {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Get Redis connection
     */
    public function getRedis(): \Redis
    {
        return $this->redis;
    }

    public function __destruct()
    {
        if ($this->redis) {
            $this->redis->close();
        }
    }
}
