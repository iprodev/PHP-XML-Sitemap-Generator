<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\ChangeDetector;
use IProDev\Sitemap\Database\Database;
use PHPUnit\Framework\TestCase;

/**
 * Runs against SQLite (in memory) and, when configured, MySQL/MariaDB and PostgreSQL:
 *
 *   SITEMAP_TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=sitemap"
 *   SITEMAP_TEST_MYSQL_USER=...  SITEMAP_TEST_MYSQL_PASSWORD=...
 *   SITEMAP_TEST_PGSQL_DSN="pgsql:host=127.0.0.1;dbname=sitemap"
 *   SITEMAP_TEST_PGSQL_USER=...  SITEMAP_TEST_PGSQL_PASSWORD=...
 *
 * The tables sitemap_urls and sitemap_crawls in those databases are dropped and recreated.
 */
class DatabaseTest extends TestCase
{
    public static function databaseProvider(): array
    {
        $cases = ['sqlite' => ['sqlite::memory:', null, null]];

        foreach (['mysql' => 'SITEMAP_TEST_MYSQL', 'pgsql' => 'SITEMAP_TEST_PGSQL'] as $name => $prefix) {
            $dsn = getenv($prefix . '_DSN');
            if ($dsn) {
                $cases[$name] = [$dsn, getenv($prefix . '_USER') ?: null, getenv($prefix . '_PASSWORD') ?: null];
            }
        }

        return $cases;
    }

    private function database(string $dsn, ?string $user, ?string $password): Database
    {
        $driver = explode(':', $dsn, 2)[0];
        if (!in_array($driver, \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped("PDO driver {$driver} is not available");
        }

        if ($driver !== 'sqlite') {
            $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('DROP TABLE IF EXISTS sitemap_urls');
            $pdo->exec('DROP TABLE IF EXISTS sitemap_crawls');
        }

        $db = new Database($dsn, $user, $password);
        $db->createTables();

        return $db;
    }

    private function page(string $url, string $hash, array $extra = []): array
    {
        return array_merge(
            ['url' => $url, 'status' => 200, 'lastmod' => '2026-01-15', 'content_hash' => $hash],
            $extra
        );
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCrawlerPagesAreStored(string $dsn, ?string $user, ?string $password): void
    {
        $db = $this->database($dsn, $user, $password);
        $crawlId = $db->startCrawl('example.com', 'https://example.com/');

        $longTitle = str_repeat('عنوان طولانی ', 100);
        $db->saveUrls($crawlId, [
            $this->page('https://example.com/', str_repeat('a', 64), [
                'title' => $longTitle,
                'word_count' => 120,
                'response_time' => 0.25,
                'depth' => 0,
            ]),
            $this->page('https://example.com/' . str_repeat('x', 1500), str_repeat('b', 64)),
        ]);

        $rows = $db->getUrlsFromCrawl($crawlId);

        $this->assertCount(2, $rows);
        $this->assertEquals(200, $rows[0]['status_code']);
        $this->assertSame('2026-01-15', $rows[0]['last_modified']);
        $this->assertSame($longTitle, $rows[0]['title']);
        $this->assertEquals(120, $rows[0]['word_count']);
        $this->assertEqualsWithDelta(0.25, (float)$rows[0]['response_time'], 0.0001);
        $this->assertSame(1520, strlen($rows[1]['url']));
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testSavingTheSameUrlTwiceUpdatesIt(string $dsn, ?string $user, ?string $password): void
    {
        $db = $this->database($dsn, $user, $password);
        $crawlId = $db->startCrawl('example.com', 'https://example.com/');

        $db->saveUrl($crawlId, $this->page('https://example.com/a', str_repeat('a', 64)));
        $db->saveUrl($crawlId, $this->page('https://example.com/a', str_repeat('b', 64), ['status' => 203]));

        $rows = $db->getUrlsFromCrawl($crawlId);
        $this->assertCount(1, $rows);
        $this->assertSame(str_repeat('b', 64), $rows[0]['content_hash']);
        $this->assertEquals(203, $rows[0]['status_code']);
        $this->assertEquals(2, $rows[0]['check_count']);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testCrawlsCanStartInTheSameSecond(string $dsn, ?string $user, ?string $password): void
    {
        $db = $this->database($dsn, $user, $password);

        $first = $db->startCrawl('example.com', 'https://example.com/');
        $second = $db->startCrawl('example.com', 'https://example.com/');

        $this->assertNotSame($first, $second);
    }

    /**
     * @dataProvider databaseProvider
     */
    public function testChangeDetection(string $dsn, ?string $user, ?string $password): void
    {
        $db = $this->database($dsn, $user, $password);

        $old = $db->startCrawl('example.com', 'https://example.com/');
        $db->saveUrls($old, [
            $this->page('https://example.com/same', str_repeat('1', 64)),
            $this->page('https://example.com/changed', str_repeat('2', 64)),
            $this->page('https://example.com/removed', str_repeat('3', 64)),
            $this->page('https://example.com/unknown-hash', str_repeat('4', 64)),
        ]);
        $db->completeCrawl($old, ['total_pages' => 4]);

        $new = $db->startCrawl('example.com', 'https://example.com/');
        $db->saveUrls($new, [
            $this->page('https://example.com/same', str_repeat('1', 64), ['lastmod' => '2026-02-01']),
            $this->page('https://example.com/changed', str_repeat('9', 64)),
            $this->page('https://example.com/added', str_repeat('5', 64)),
            ['url' => 'https://example.com/unknown-hash', 'status' => 200],
        ]);
        $db->completeCrawl($new, ['total_pages' => 4]);

        $this->assertSame($old, (int)$db->getPreviousCrawl('example.com', $new)['id']);

        $changes = (new ChangeDetector($db))->detectChanges($old, $new);

        $this->assertSame(['https://example.com/added'], array_column($changes['new'], 'url'));
        $this->assertSame(['https://example.com/changed'], array_column($changes['modified'], 'url'));
        $this->assertSame(['https://example.com/removed'], array_column($changes['deleted'], 'url'));
        $this->assertSame(3, $changes['summary']['total_changes']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testOldSqliteSchemaIsUpgraded(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sitemap_db');

        try {
            // Schema of version 3.0.0
            $pdo = new \PDO('sqlite:' . $file);
            $pdo->exec("CREATE TABLE sitemap_crawls (
                id INTEGER PRIMARY KEY AUTOINCREMENT, domain VARCHAR(255) NOT NULL,
                start_url VARCHAR(500) NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME,
                status VARCHAR(50) DEFAULT 'running', total_pages INTEGER DEFAULT 0, new_pages INTEGER DEFAULT 0,
                modified_pages INTEGER DEFAULT 0, deleted_pages INTEGER DEFAULT 0, errors INTEGER DEFAULT 0,
                config TEXT, UNIQUE(domain, started_at))");
            $pdo->exec("CREATE TABLE sitemap_urls (
                id INTEGER PRIMARY KEY AUTOINCREMENT, crawl_id INTEGER NOT NULL, url VARCHAR(1000) NOT NULL,
                status_code INTEGER, last_modified DATETIME, content_hash VARCHAR(64), title VARCHAR(500),
                meta_description TEXT, h1 VARCHAR(500), word_count INTEGER, image_count INTEGER,
                link_count INTEGER, depth INTEGER, response_time REAL, content_size INTEGER,
                is_noindex BOOLEAN DEFAULT 0, is_nofollow BOOLEAN DEFAULT 0, canonical_url VARCHAR(1000),
                first_seen DATETIME NOT NULL, last_seen DATETIME NOT NULL, check_count INTEGER DEFAULT 1,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(crawl_id, url))");
            $pdo->exec("INSERT INTO sitemap_crawls (domain, start_url, started_at, status)
                VALUES ('example.com', 'https://example.com/', '2025-01-01 00:00:00', 'completed')");
            $pdo->exec("INSERT INTO sitemap_urls (crawl_id, url, first_seen, last_seen, created_at, updated_at)
                VALUES (1, 'https://example.com/old', '2025-01-01', '2025-01-01', '2025-01-01', '2025-01-01')");
            $pdo = null;

            $db = new Database('sqlite:' . $file);
            $db->createTables();

            $row = $db->getUrlsFromCrawl(1)[0];
            $this->assertSame(Database::hashUrl('https://example.com/old'), $row['url_hash']);

            $crawlId = $db->startCrawl('example.com', 'https://example.com/');
            $db->saveUrls($crawlId, [$this->page('https://example.com/old', str_repeat('a', 64))]);
            $changes = (new ChangeDetector($db))->detectChanges(1, $crawlId);
            $this->assertSame([], $changes['new']);
        } finally {
            unlink($file);
        }
    }

    public function testUnsupportedDriverIsRejected(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('connection failed');
        new Database('nosuchdriver:whatever');
    }
}
