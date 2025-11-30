<?php

namespace IProDev\Sitemap\Database;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;
    private string $table = 'sitemap_urls';
    private string $crawlTable = 'sitemap_crawls';

    public function __construct(string $dsn, string $username = null, string $password = null)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Create database tables
     */
    public function createTables(): void
    {
        // Crawls table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->crawlTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain VARCHAR(255) NOT NULL,
            start_url VARCHAR(500) NOT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME,
            status VARCHAR(50) DEFAULT 'running',
            total_pages INTEGER DEFAULT 0,
            new_pages INTEGER DEFAULT 0,
            modified_pages INTEGER DEFAULT 0,
            deleted_pages INTEGER DEFAULT 0,
            errors INTEGER DEFAULT 0,
            config TEXT,
            UNIQUE(domain, started_at)
        )";
        $this->pdo->exec($sql);

        // URLs table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            crawl_id INTEGER NOT NULL,
            url VARCHAR(1000) NOT NULL,
            status_code INTEGER,
            last_modified DATETIME,
            content_hash VARCHAR(64),
            title VARCHAR(500),
            meta_description TEXT,
            h1 VARCHAR(500),
            word_count INTEGER,
            image_count INTEGER,
            link_count INTEGER,
            depth INTEGER,
            response_time REAL,
            content_size INTEGER,
            is_noindex BOOLEAN DEFAULT 0,
            is_nofollow BOOLEAN DEFAULT 0,
            canonical_url VARCHAR(1000),
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            check_count INTEGER DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (crawl_id) REFERENCES {$this->crawlTable}(id),
            UNIQUE(crawl_id, url)
        )";
        $this->pdo->exec($sql);

        // Indexes
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_url ON {$this->table}(url)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_domain ON {$this->crawlTable}(domain)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON {$this->table}(status_code)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_last_modified ON {$this->table}(last_modified)");
    }

    /**
     * Start new crawl
     */
    public function startCrawl(string $domain, string $startUrl, array $config = []): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->crawlTable} 
            (domain, start_url, started_at, config, status) 
            VALUES (?, ?, datetime('now'), ?, 'running')"
        );
        
        $stmt->execute([$domain, $startUrl, json_encode($config)]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Complete crawl
     */
    public function completeCrawl(int $crawlId, array $stats): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->crawlTable} SET 
            completed_at = datetime('now'),
            status = 'completed',
            total_pages = ?,
            new_pages = ?,
            modified_pages = ?,
            deleted_pages = ?,
            errors = ?
            WHERE id = ?"
        );
        
        $stmt->execute([
            $stats['total_pages'] ?? 0,
            $stats['new_pages'] ?? 0,
            $stats['modified_pages'] ?? 0,
            $stats['deleted_pages'] ?? 0,
            $stats['errors'] ?? 0,
            $crawlId
        ]);
    }

    /**
     * Save URL
     */
    public function saveUrl(int $crawlId, array $data): void
    {
        $existing = $this->getUrlByCrawlAndUrl($crawlId, $data['url']);
        
        $now = date('Y-m-d H:i:s');
        
        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET
                status_code = ?,
                last_modified = ?,
                content_hash = ?,
                title = ?,
                meta_description = ?,
                h1 = ?,
                word_count = ?,
                image_count = ?,
                link_count = ?,
                depth = ?,
                response_time = ?,
                content_size = ?,
                is_noindex = ?,
                is_nofollow = ?,
                canonical_url = ?,
                last_seen = ?,
                check_count = check_count + 1,
                updated_at = ?
                WHERE id = ?"
            );
            
            $stmt->execute([
                $data['status_code'] ?? null,
                $data['last_modified'] ?? null,
                $data['content_hash'] ?? null,
                $data['title'] ?? null,
                $data['meta_description'] ?? null,
                $data['h1'] ?? null,
                $data['word_count'] ?? null,
                $data['image_count'] ?? null,
                $data['link_count'] ?? null,
                $data['depth'] ?? null,
                $data['response_time'] ?? null,
                $data['content_size'] ?? null,
                $data['is_noindex'] ?? 0,
                $data['is_nofollow'] ?? 0,
                $data['canonical_url'] ?? null,
                $now,
                $now,
                $existing['id']
            ]);
        } else {
            // Insert
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table} 
                (crawl_id, url, status_code, last_modified, content_hash, title, 
                meta_description, h1, word_count, image_count, link_count, depth, 
                response_time, content_size, is_noindex, is_nofollow, canonical_url,
                first_seen, last_seen, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->execute([
                $crawlId,
                $data['url'],
                $data['status_code'] ?? null,
                $data['last_modified'] ?? null,
                $data['content_hash'] ?? null,
                $data['title'] ?? null,
                $data['meta_description'] ?? null,
                $data['h1'] ?? null,
                $data['word_count'] ?? null,
                $data['image_count'] ?? null,
                $data['link_count'] ?? null,
                $data['depth'] ?? null,
                $data['response_time'] ?? null,
                $data['content_size'] ?? null,
                $data['is_noindex'] ?? 0,
                $data['is_nofollow'] ?? 0,
                $data['canonical_url'] ?? null,
                $now,
                $now,
                $now,
                $now
            ]);
        }
    }

    /**
     * Get URL by crawl and URL
     */
    private function getUrlByCrawlAndUrl(int $crawlId, string $url): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE crawl_id = ? AND url = ?"
        );
        $stmt->execute([$crawlId, $url]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Get previous crawl for domain
     */
    public function getPreviousCrawl(string $domain, int $excludeCrawlId = null): ?array
    {
        $sql = "SELECT * FROM {$this->crawlTable} 
                WHERE domain = ? AND status = 'completed'";
        
        $params = [$domain];
        
        if ($excludeCrawlId) {
            $sql .= " AND id != ?";
            $params[] = $excludeCrawlId;
        }
        
        $sql .= " ORDER BY completed_at DESC LIMIT 1";
        
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
     * Get changed URLs between two crawls
     */
    public function getChangedUrls(int $oldCrawlId, int $newCrawlId): array
    {
        $sql = "SELECT 
                n.url,
                n.content_hash as new_hash,
                o.content_hash as old_hash,
                n.last_modified as new_modified,
                o.last_modified as old_modified,
                'modified' as change_type
            FROM {$this->table} n
            INNER JOIN {$this->table} o ON n.url = o.url
            WHERE n.crawl_id = ? AND o.crawl_id = ?
            AND (n.content_hash != o.content_hash OR n.last_modified != o.last_modified)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newCrawlId, $oldCrawlId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Get new URLs (not in previous crawl)
     */
    public function getNewUrls(int $oldCrawlId, int $newCrawlId): array
    {
        $sql = "SELECT n.* FROM {$this->table} n
            LEFT JOIN {$this->table} o ON n.url = o.url AND o.crawl_id = ?
            WHERE n.crawl_id = ? AND o.id IS NULL";
        
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
            LEFT JOIN {$this->table} n ON o.url = n.url AND n.crawl_id = ?
            WHERE o.crawl_id = ? AND n.id IS NULL";
        
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
}
