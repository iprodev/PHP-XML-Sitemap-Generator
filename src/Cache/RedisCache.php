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

        return unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            serialize($value)
        );
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
            $result[$key] = $values[$i] !== false ? unserialize($values[$i]) : null;
        }

        return $result;
    }

    public function setMultiple(array $items, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $pipe = $this->redis->multi(\Redis::PIPELINE);

        foreach ($items as $key => $value) {
            $pipe->setex($this->prefix . $key, $ttl, serialize($value));
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
