<?php

namespace IProDev\Sitemap\Database;

use IProDev\Sitemap\Utils;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Stores crawls and their URLs in SQLite, MySQL/MariaDB or PostgreSQL.
 *
 * URLs are identified by a SHA-256 hash of the URL (url_hash), which keeps
 * unique keys and joins short and portable across databases. All times are
 * stored in UTC with microseconds.
 */
class Database
{
    private const SUPPORTED_DRIVERS = ['sqlite', 'mysql', 'pgsql'];

    /**
     * Columns of a URL row that can be set from page data
     */
    private const URL_FIELDS = [
        'status_code', 'last_modified', 'content_hash', 'title', 'meta_description', 'h1',
        'word_count', 'image_count', 'link_count', 'depth', 'response_time', 'content_size',
        'is_noindex', 'is_nofollow', 'canonical_url',
    ];

    private PDO $pdo;
    private string $driver;
    private string $table = 'sitemap_urls';
    private string $crawlTable = 'sitemap_crawls';
    private ?PDOStatement $upsertStatement = null;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            // The message can contain the DSN; do not echo credentials
            throw new \RuntimeException("Database connection failed: " . Utils::redactUrl($e->getMessage()));
        }

        $this->driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($this->driver, self::SUPPORTED_DRIVERS, true)) {
            throw new \RuntimeException(
                "Unsupported database driver '{$this->driver}'. Supported: " . implode(', ', self::SUPPORTED_DRIVERS)
            );
        }

        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        } elseif ($this->driver === 'mysql') {
            $this->pdo->exec("SET NAMES utf8mb4");
        }
    }

    /**
     * Create database tables (and upgrade tables created by older versions)
     */
    public function createTables(): void
    {
        foreach ($this->schema() as $sql) {
            $this->pdo->exec($sql);
        }

        // Upgrade old tables before creating indexes on new columns
        $this->migrate();

        foreach ($this->indexes() as $sql) {
            $this->pdo->exec($sql);
        }
    }

    /**
     * Indexes created after the tables (MySQL declares them inside CREATE TABLE)
     *
     * @return string[]
     */
    private function indexes(): array
    {
        if ($this->driver === 'mysql') {
            return [];
        }

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_sitemap_crawls_domain ON {$this->crawlTable}(domain, status)",
            "CREATE INDEX IF NOT EXISTS idx_sitemap_urls_url_hash ON {$this->table}(url_hash)",
            "CREATE INDEX IF NOT EXISTS idx_sitemap_urls_status ON {$this->table}(status_code)",
        ];

        if ($this->driver === 'sqlite') {
            // Tables from old versions have no unique key on url_hash
            $indexes[] = "CREATE UNIQUE INDEX IF NOT EXISTS uniq_sitemap_urls_crawl_url"
                . " ON {$this->table}(crawl_id, url_hash)";
        }

        return $indexes;
    }

    /**
     * @return string[]
     */
    private function schema(): array
    {
        $c = $this->crawlTable;
        $u = $this->table;

        switch ($this->driver) {
            case 'mysql':
                return [
                    "CREATE TABLE IF NOT EXISTS {$c} (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        domain VARCHAR(255) NOT NULL,
                        start_url TEXT NOT NULL,
                        started_at DATETIME(6) NOT NULL,
                        completed_at DATETIME(6) NULL,
                        status VARCHAR(50) NOT NULL DEFAULT 'running',
                        total_pages INT NOT NULL DEFAULT 0,
                        new_pages INT NOT NULL DEFAULT 0,
                        modified_pages INT NOT NULL DEFAULT 0,
                        deleted_pages INT NOT NULL DEFAULT 0,
                        errors INT NOT NULL DEFAULT 0,
                        config TEXT,
                        INDEX idx_sitemap_crawls_domain (domain, status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                    "CREATE TABLE IF NOT EXISTS {$u} (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        crawl_id INT UNSIGNED NOT NULL,
                        url TEXT NOT NULL,
                        url_hash CHAR(64) NOT NULL,
                        status_code INT NULL,
                        last_modified VARCHAR(40) NULL,
                        content_hash CHAR(64) NULL,
                        title TEXT NULL,
                        meta_description TEXT NULL,
                        h1 TEXT NULL,
                        word_count INT NULL,
                        image_count INT NULL,
                        link_count INT NULL,
                        depth INT NULL,
                        response_time DOUBLE NULL,
                        content_size INT NULL,
                        is_noindex SMALLINT NOT NULL DEFAULT 0,
                        is_nofollow SMALLINT NOT NULL DEFAULT 0,
                        canonical_url TEXT NULL,
                        first_seen DATETIME(6) NOT NULL,
                        last_seen DATETIME(6) NOT NULL,
                        check_count INT NOT NULL DEFAULT 1,
                        created_at DATETIME(6) NOT NULL,
                        updated_at DATETIME(6) NOT NULL,
                        UNIQUE KEY uniq_sitemap_urls_crawl_url (crawl_id, url_hash),
                        INDEX idx_sitemap_urls_url_hash (url_hash),
                        INDEX idx_sitemap_urls_status (status_code),
                        CONSTRAINT fk_sitemap_urls_crawl FOREIGN KEY (crawl_id) REFERENCES {$c}(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                ];

            case 'pgsql':
                return [
                    "CREATE TABLE IF NOT EXISTS {$c} (
                        id SERIAL PRIMARY KEY,
                        domain VARCHAR(255) NOT NULL,
                        start_url TEXT NOT NULL,
                        started_at TIMESTAMP(6) NOT NULL,
                        completed_at TIMESTAMP(6) NULL,
                        status VARCHAR(50) NOT NULL DEFAULT 'running',
                        total_pages INTEGER NOT NULL DEFAULT 0,
                        new_pages INTEGER NOT NULL DEFAULT 0,
                        modified_pages INTEGER NOT NULL DEFAULT 0,
                        deleted_pages INTEGER NOT NULL DEFAULT 0,
                        errors INTEGER NOT NULL DEFAULT 0,
                        config TEXT
                    )",
                    "CREATE TABLE IF NOT EXISTS {$u} (
                        id SERIAL PRIMARY KEY,
                        crawl_id INTEGER NOT NULL REFERENCES {$c}(id) ON DELETE CASCADE,
                        url TEXT NOT NULL,
                        url_hash CHAR(64) NOT NULL,
                        status_code INTEGER NULL,
                        last_modified VARCHAR(40) NULL,
                        content_hash CHAR(64) NULL,
                        title TEXT NULL,
                        meta_description TEXT NULL,
                        h1 TEXT NULL,
                        word_count INTEGER NULL,
                        image_count INTEGER NULL,
                        link_count INTEGER NULL,
                        depth INTEGER NULL,
                        response_time DOUBLE PRECISION NULL,
                        content_size INTEGER NULL,
                        is_noindex SMALLINT NOT NULL DEFAULT 0,
                        is_nofollow SMALLINT NOT NULL DEFAULT 0,
                        canonical_url TEXT NULL,
                        first_seen TIMESTAMP(6) NOT NULL,
                        last_seen TIMESTAMP(6) NOT NULL,
                        check_count INTEGER NOT NULL DEFAULT 1,
                        created_at TIMESTAMP(6) NOT NULL,
                        updated_at TIMESTAMP(6) NOT NULL,
                        CONSTRAINT uniq_sitemap_urls_crawl_url UNIQUE (crawl_id, url_hash)
                    )",
                ];

            default: // sqlite
                return [
                    "CREATE TABLE IF NOT EXISTS {$c} (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        domain VARCHAR(255) NOT NULL,
                        start_url TEXT NOT NULL,
                        started_at DATETIME NOT NULL,
                        completed_at DATETIME,
                        status VARCHAR(50) DEFAULT 'running',
                        total_pages INTEGER DEFAULT 0,
                        new_pages INTEGER DEFAULT 0,
                        modified_pages INTEGER DEFAULT 0,
                        deleted_pages INTEGER DEFAULT 0,
                        errors INTEGER DEFAULT 0,
                        config TEXT
                    )",
                    "CREATE TABLE IF NOT EXISTS {$u} (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        crawl_id INTEGER NOT NULL,
                        url TEXT NOT NULL,
                        url_hash CHAR(64) NOT NULL,
                        status_code INTEGER,
                        last_modified VARCHAR(40),
                        content_hash CHAR(64),
                        title TEXT,
                        meta_description TEXT,
                        h1 TEXT,
                        word_count INTEGER,
                        image_count INTEGER,
                        link_count INTEGER,
                        depth INTEGER,
                        response_time REAL,
                        content_size INTEGER,
                        is_noindex SMALLINT NOT NULL DEFAULT 0,
                        is_nofollow SMALLINT NOT NULL DEFAULT 0,
                        canonical_url TEXT,
                        first_seen DATETIME NOT NULL,
                        last_seen DATETIME NOT NULL,
                        check_count INTEGER DEFAULT 1,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        FOREIGN KEY (crawl_id) REFERENCES {$c}(id) ON DELETE CASCADE
                    )",
                ];
        }
    }

    /**
     * Add url_hash to URL tables created by versions before it existed (only SQLite
     * was supported then; MySQL and PostgreSQL tables always have it).
     */
    private function migrate(): void
    {
        if ($this->driver !== 'sqlite') {
            return;
        }

        $columns = array_column($this->pdo->query("PRAGMA table_info({$this->table})")->fetchAll(), 'name');
        if (in_array('url_hash', $columns, true)) {
            return;
        }

        $this->pdo->exec("ALTER TABLE {$this->table} ADD COLUMN url_hash CHAR(64)");

        $update = $this->pdo->prepare("UPDATE {$this->table} SET url_hash = ? WHERE id = ?");
        $this->pdo->beginTransaction();
        foreach ($this->pdo->query("SELECT id, url FROM {$this->table}")->fetchAll() as $row) {
            $update->execute([self::hashUrl($row['url']), $row['id']]);
        }
        $this->pdo->commit();
    }

    /**
     * Start new crawl. Secrets in $config are redacted before it is stored.
     */
    public function startCrawl(string $domain, string $startUrl, array $config = []): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->crawlTable}
            (domain, start_url, started_at, config, status)
            VALUES (?, ?, ?, ?, 'running')"
        );

        $stmt->execute([$domain, $startUrl, $this->now(), json_encode(Utils::redactConfig($config))]);

        $id = $this->driver === 'pgsql'
            ? $this->pdo->lastInsertId("{$this->crawlTable}_id_seq")
            : $this->pdo->lastInsertId();

        return (int)$id;
    }

    /**
     * Complete crawl
     */
    public function completeCrawl(int $crawlId, array $stats, string $status = 'completed'): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->crawlTable} SET
            completed_at = ?,
            status = ?,
            total_pages = ?,
            new_pages = ?,
            modified_pages = ?,
            deleted_pages = ?,
            errors = ?
            WHERE id = ?"
        );

        $stmt->execute([
            $this->now(),
            $status,
            $stats['total_pages'] ?? 0,
            $stats['new_pages'] ?? 0,
            $stats['modified_pages'] ?? 0,
            $stats['deleted_pages'] ?? 0,
            $stats['errors'] ?? 0,
            $crawlId
        ]);
    }

    /**
     * Save (insert or update) a URL of a crawl.
     *
     * Accepts the page records produced by the Crawler ("status", "lastmod")
     * as well as the column names ("status_code", "last_modified").
     */
    public function saveUrl(int $crawlId, array $data): void
    {
        if (!isset($data['url']) || !is_string($data['url']) || $data['url'] === '') {
            throw new \InvalidArgumentException('URL data must contain a non-empty "url"');
        }

        $values = $this->urlValues($data);
        $now = $this->now();

        $params = [$crawlId, $data['url'], self::hashUrl($data['url'])];
        foreach (self::URL_FIELDS as $field) {
            $params[] = $values[$field];
        }
        array_push($params, $now, $now, $now, $now);

        $this->upsertStatement()->execute($params);
    }

    /**
     * Save many URLs of a crawl in one transaction
     *
     * @param array<int, array> $pages
     */
    public function saveUrls(int $crawlId, array $pages): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($pages as $page) {
                $this->saveUrl($crawlId, $page);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function upsertStatement(): PDOStatement
    {
        if ($this->upsertStatement !== null) {
            return $this->upsertStatement;
        }

        $columns = array_merge(
            ['crawl_id', 'url', 'url_hash'],
            self::URL_FIELDS,
            ['first_seen', 'last_seen', 'created_at', 'updated_at']
        );
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updateColumns = array_merge(self::URL_FIELDS, ['last_seen', 'updated_at']);

        if ($this->driver === 'mysql') {
            $updates = array_map(fn($col) => "{$col} = VALUES({$col})", $updateColumns);
            $updates[] = 'check_count = check_count + 1';
            $conflict = 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        } else {
            $updates = array_map(fn($col) => "{$col} = excluded.{$col}", $updateColumns);
            $updates[] = "check_count = {$this->table}.check_count + 1";
            $conflict = 'ON CONFLICT (crawl_id, url_hash) DO UPDATE SET ' . implode(', ', $updates);
        }

        $this->upsertStatement = $this->pdo->prepare(
            "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders}) {$conflict}"
        );

        return $this->upsertStatement;
    }

    /**
     * @return array<string, mixed> values for URL_FIELDS
     */
    private function urlValues(array $data): array
    {
        $aliases = ['status_code' => 'status', 'last_modified' => 'lastmod', 'canonical_url' => 'canonical'];

        $values = [];
        foreach (self::URL_FIELDS as $field) {
            $value = $data[$field] ?? (isset($aliases[$field]) ? ($data[$aliases[$field]] ?? null) : null);
            if (in_array($field, ['is_noindex', 'is_nofollow'], true)) {
                $value = $value ? 1 : 0;
            }
            $values[$field] = $value;
        }

        return $values;
    }

    /**
     * Get previous crawl for domain
     */
    public function getPreviousCrawl(string $domain, ?int $excludeCrawlId = null): ?array
    {
        $sql = "SELECT * FROM {$this->crawlTable}
                WHERE domain = ? AND status = 'completed'";

        $params = [$domain];

        if ($excludeCrawlId) {
            $sql .= " AND id != ?";
            $params[] = $excludeCrawlId;
        }

        $sql .= " ORDER BY completed_at DESC, id DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Get URLs from crawl
     */
    public function getUrlsFromCrawl(int $crawlId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE crawl_id = ? ORDER BY url"
        );
        $stmt->execute([$crawlId]);

        return $stmt->fetchAll();
    }

    /**
     * URLs present in both crawls whose content changed (both content hashes known and different)
     */
    public function getChangedUrls(int $oldCrawlId, int $newCrawlId): array
    {
        $sql = "SELECT
                n.url,
                n.content_hash AS new_hash,
                o.content_hash AS old_hash,
                n.last_modified AS new_modified,
                o.last_modified AS old_modified,
                'modified' AS change_type
            FROM {$this->table} n
            INNER JOIN {$this->table} o ON n.url_hash = o.url_hash AND o.crawl_id = ?
            WHERE n.crawl_id = ?
            AND n.content_hash IS NOT NULL
            AND o.content_hash IS NOT NULL
            AND n.content_hash <> o.content_hash
            ORDER BY n.url";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$oldCrawlId, $newCrawlId]);

        return $stmt->fetchAll();
    }

    /**
     * Get new URLs (not in previous crawl)
     */
    public function getNewUrls(int $oldCrawlId, int $newCrawlId): array
    {
        $sql = "SELECT n.* FROM {$this->table} n
            LEFT JOIN {$this->table} o ON n.url_hash = o.url_hash AND o.crawl_id = ?
            WHERE n.crawl_id = ? AND o.id IS NULL
            ORDER BY n.url";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$oldCrawlId, $newCrawlId]);

        return $stmt->fetchAll();
    }

    /**
     * Get deleted URLs (in previous but not in current)
     */
    public function getDeletedUrls(int $oldCrawlId, int $newCrawlId): array
    {
        $sql = "SELECT o.* FROM {$this->table} o
            LEFT JOIN {$this->table} n ON o.url_hash = n.url_hash AND n.crawl_id = ?
            WHERE o.crawl_id = ? AND n.id IS NULL
            ORDER BY o.url";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newCrawlId, $oldCrawlId]);

        return $stmt->fetchAll();
    }

    /**
     * Get statistics
     */
    public function getStats(int $crawlId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) as total_urls,
                AVG(response_time) as avg_response_time,
                AVG(content_size) as avg_content_size,
                AVG(word_count) as avg_word_count,
                COUNT(CASE WHEN status_code = 200 THEN 1 END) as success_count,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_count,
                COUNT(CASE WHEN is_noindex = 1 THEN 1 END) as noindex_count
            FROM {$this->table}
            WHERE crawl_id = ?"
        );

        $stmt->execute([$crawlId]);
        return $stmt->fetch();
    }

    /**
     * Get PDO instance
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Name of the PDO driver (sqlite, mysql or pgsql)
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Current UTC time with microseconds (unique enough to order crawls started in the same second)
     */
    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
