<?php

namespace IProDev\Sitemap\Tests;

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\Cache\FileCache;

class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/sitemap_cache_test_' . uniqid();
        $this->cache = new FileCache($this->cacheDir, 3600);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->cacheDir);
        }
    }

    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $value = ['data' => 'test value'];

        $this->cache->set($key, $value);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($value, $retrieved);
    }

    public function testHas(): void
    {
        $key = 'test_key';
        $value = 'test value';

        $this->assertFalse($this->cache->has($key));

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
    }

    public function testDelete(): void
    {
        $key = 'test_key';
        $value = 'test value';

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));

        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }

    public function testExpiration(): void
    {
        $cache = new FileCache($this->cacheDir, 1); // 1 second TTL
        $key = 'test_key';
        $value = 'test value';

        $cache->set($key, $value);
        $this->assertTrue($cache->has($key));

        sleep(2);
        $this->assertNull($cache->get($key));
    }

    public function testMultiple(): void
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $this->cache->setMultiple($items);
        $retrieved = $this->cache->getMultiple(array_keys($items));

        $this->assertEquals($items, $retrieved);
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->assertTrue($this->cache->clear());
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testCleanup(): void
    {
        $cache = new FileCache($this->cacheDir, 1);

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        sleep(2);

        $deleted = $cache->cleanup();
        $this->assertEquals(2, $deleted);
    }

    public function testStats(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $stats = $this->cache->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_entries', $stats);
        $this->assertArrayHasKey('valid_entries', $stats);
        $this->assertEquals(2, $stats['total_entries']);
    }

    public function testTamperedCacheFileCannotInstantiateObjects(): void
    {
        // A PHP-serialized object planted in the cache directory must not be unserialized
        $file = $this->cacheDir . '/' . md5('planted') . '.cache';
        file_put_contents($file, 'O:8:"stdClass":2:{s:5:"value";s:1:"x";s:7:"expires";i:9999999999;}');

        $this->assertNull($this->cache->get('planted'));
        $this->assertFileDoesNotExist($file);
    }

    public function testCorruptCacheFileIsTreatedAsMiss(): void
    {
        $read = $this->cacheDir . '/' . md5('corrupt') . '.cache';
        $unread = $this->cacheDir . '/' . md5('corrupt-2') . '.cache';
        file_put_contents($read, '{not json');
        file_put_contents($unread, '{not json');

        // Reading a corrupt entry is a miss and removes it
        $this->assertNull($this->cache->get('corrupt'));
        $this->assertFileDoesNotExist($read);

        // cleanup() removes the remaining corrupt entry
        $this->assertSame(1, $this->cache->cleanup());
        $this->assertFileDoesNotExist($unread);
    }

    public function testValueThatCannotBeEncodedIsRejected(): void
    {
        $this->assertFalse($this->cache->set('nan', NAN));
        $this->assertNull($this->cache->get('nan'));
    }
}
