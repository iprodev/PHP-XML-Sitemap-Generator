# Complete Features Documentation

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)
[![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)](CHANGELOG.md)

This document provides comprehensive documentation for all features available in PHP XML Sitemap Generator v3.0.

## 📋 Overview

The sitemap generator provides enterprise-grade features for crawling websites and generating XML sitemaps. It's designed for both small websites and large-scale applications with millions of pages.

### Key Capabilities

| Category | Features |
|----------|----------|
| **Performance** | Concurrent requests, caching (File/Redis), rate limiting |
| **Storage** | SQLite/MySQL/PostgreSQL database, change detection |
| **Analysis** | SEO scoring, content quality, duplicate detection |
| **Sitemaps** | Standard, Image, Video, News sitemaps |
| **Advanced** | JavaScript rendering, proxy rotation, webhooks |
| **Operations** | Resume capability, scheduled crawling, metrics |

### Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | >= 8.0 | Required |
| ext-curl | * | HTTP requests |
| ext-xml | * | XML generation |
| ext-mbstring | * | String handling |
| ext-zlib | * | Gzip compression |
| ext-pdo | * | Database storage |
| ext-redis | * | Optional: Redis cache |
| ext-posix | * | Optional: Headless browser |
| Chrome/Chromium | Any | Optional: JS rendering |

### Quick Start

```php
<?php
require 'vendor/autoload.php';

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

// Initialize components
$fetcher = new Fetcher(['concurrency' => 10]);
$robots = RobotsTxt::fromUrl('https://example.com', $fetcher);
$crawler = new Crawler($fetcher, $robots);

// Crawl the site
$pages = $crawler->crawl('https://example.com', 1000, 3);

// Generate sitemap
SitemapWriter::write($pages, './output', 50000, 'https://example.com');
```

Or use the CLI:

```bash
php bin/sitemap --url=https://example.com --out=./sitemaps --concurrency=10
```

---

## 📚 Table of Contents

1. [Cache System](#1-cache-system)
2. [Database Storage](#2-database-storage)
3. [Change Detection](#3-change-detection)
4. [Sitemap Types](#4-sitemap-types)
5. [Rate Limiting](#5-rate-limiting)
6. [Scheduled Crawling](#6-scheduled-crawling)
7. [SEO Analyzer](#7-seo-analyzer)
8. [Content Quality Checker](#8-content-quality-checker)
9. [Smart Filtering](#9-smart-filtering)
10. [Distributed Crawling](#10-distributed-crawling)
11. [Resume Capability](#11-resume-capability)
12. [Webhook Notifications](#12-webhook-notifications)
13. [Performance Metrics](#13-performance-metrics)
14. [Interactive Mode](#14-interactive-mode)
15. [Proxy Support](#15-proxy-support)
16. [JavaScript Rendering](#16-javascript-rendering)
17. [Environment Variables](#17-environment-variables)
18. [Complete Example](#18-complete-example)

---

## 1. Cache System

### Overview
Reduce redundant HTTP requests by caching responses.

### Supported Drivers
- **File Cache**: Stores cache in local files
- **Redis Cache**: Uses Redis for distributed caching

### Usage

#### File Cache

```php
use IProDev\Sitemap\Cache\FileCache;

$cache = new FileCache('./cache', 3600); // TTL: 1 hour

// Get
$value = $cache->get('my-key');

// Set
$cache->set('my-key', $data, 3600);

// Check existence
if ($cache->has('my-key')) {
    // ...
}

// Delete
$cache->delete('my-key');

// Clear all
$cache->clear();

// Statistics
$stats = $cache->getStats();
```

#### Redis Cache

```php
use IProDev\Sitemap\Cache\RedisCache;

$cache = new RedisCache(
    host: '127.0.0.1',
    port: 6379,
    password: null,
    database: 0,
    prefix: 'sitemap:',
    defaultTtl: 3600
);

// Same API as FileCache
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --cache-enabled \
  --cache-driver=redis \
  --cache-ttl=3600
```

### Benefits
- ⚡ 30-50% faster for repeated crawls
- 💾 Reduces server load
- 🌐 Distributed caching with Redis

---

## 2. Database Storage

### Overview
Store crawl results in a database for historical tracking and analysis.

### Supported Databases
- SQLite (default)
- MySQL
- PostgreSQL

### Schema

```sql
-- Crawls table
CREATE TABLE sitemap_crawls (
    id INTEGER PRIMARY KEY,
    domain VARCHAR(255),
    start_url VARCHAR(500),
    started_at DATETIME,
    completed_at DATETIME,
    status VARCHAR(50),
    total_pages INTEGER,
    new_pages INTEGER,
    modified_pages INTEGER,
    deleted_pages INTEGER,
    errors INTEGER,
    config TEXT
);

-- URLs table
CREATE TABLE sitemap_urls (
    id INTEGER PRIMARY KEY,
    crawl_id INTEGER,
    url VARCHAR(1000),
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
    is_noindex BOOLEAN,
    is_nofollow BOOLEAN,
    canonical_url VARCHAR(1000),
    first_seen DATETIME,
    last_seen DATETIME,
    check_count INTEGER,
    created_at DATETIME,
    updated_at DATETIME
);
```

### Usage

```php
use IProDev\Sitemap\Database\Database;

// Initialize
$db = new Database('sqlite:./sitemap.db');
// or
$db = new Database('mysql:host=localhost;dbname=sitemap', 'user', 'pass');

// Create tables
$db->createTables();

// Start crawl
$crawlId = $db->startCrawl('example.com', 'https://example.com', $config);

// Save URL
$db->saveUrl($crawlId, [
    'url' => 'https://example.com/page',
    'status_code' => 200,
    'title' => 'Page Title',
    'content_hash' => hash('sha256', $content),
    // ... more fields
]);

// Complete crawl
$db->completeCrawl($crawlId, [
    'total_pages' => 100,
    'new_pages' => 10,
    'modified_pages' => 5,
    'deleted_pages' => 2,
    'errors' => 0
]);

// Query
$pages = $db->getUrlsFromCrawl($crawlId);
$stats = $db->getStats($crawlId);
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --db-enabled \
  --db-dsn="sqlite:./sitemap.db"
```

---

## 3. Change Detection

### Overview
Compare crawls to detect new, modified, and deleted URLs.

### Features
- Detects new URLs
- Identifies modified content
- Finds deleted URLs
- Generates change reports

### Usage

```php
use IProDev\Sitemap\ChangeDetector;

$detector = new ChangeDetector($db);

// Detect changes between two crawls
$changes = $detector->detectChanges($oldCrawlId, $newCrawlId);

// Results
print_r($changes['new']);        // New URLs
print_r($changes['modified']);   // Modified URLs
print_r($changes['deleted']);    // Deleted URLs
print_r($changes['summary']);    // Summary statistics

// Generate reports
$textReport = $detector->generateReport($oldCrawlId, $newCrawlId, 'text');
$htmlReport = $detector->generateReport($oldCrawlId, $newCrawlId, 'html');
$jsonReport = $detector->generateReport($oldCrawlId, $newCrawlId, 'json');
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --db-enabled \
  --detect-changes \
  --only-changed  # Only include changed URLs in sitemap
```

### Report Example

```
SITEMAP CHANGE DETECTION REPORT
======================================================================

SUMMARY
----------------------------------------------------------------------
New URLs:      15
Modified URLs: 8
Deleted URLs:  3
Total Changes: 26

NEW URLs
----------------------------------------------------------------------
  + https://example.com/new-page-1
  + https://example.com/new-page-2

MODIFIED URLs
----------------------------------------------------------------------
  ~ https://example.com/updated-page-1
  ~ https://example.com/updated-page-2

DELETED URLs
----------------------------------------------------------------------
  - https://example.com/old-page-1
  - https://example.com/old-page-2
```

---

## 4. Sitemap Types

### Standard XML Sitemap

Default sitemap format for all URLs.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/page</loc>
    <lastmod>2025-01-20</lastmod>
    <priority>0.8</priority>
  </url>
</urlset>
```

### Image Sitemap

For images on your pages.

```php
use IProDev\Sitemap\Sitemap\ImageSitemapWriter;

$pagesWithImages = [
    [
        'url' => 'https://example.com/gallery',
        'images' => [
            [
                'url' => 'https://example.com/image1.jpg',
                'title' => 'Beautiful Sunset',
                'caption' => 'Sunset at the beach'
            ]
        ]
    ]
];

$files = ImageSitemapWriter::write($pagesWithImages, './sitemaps');
```

### Video Sitemap

For video content.

```php
use IProDev\Sitemap\Sitemap\VideoSitemapWriter;

$pagesWithVideos = [
    [
        'url' => 'https://example.com/videos/tutorial',
        'videos' => [
            [
                'thumbnail' => 'https://example.com/thumb.jpg',
                'title' => 'How to Tutorial',
                'description' => 'Learn the basics',
                'content_url' => 'https://example.com/video.mp4',
                'duration' => 600,
                'family_friendly' => true
            ]
        ]
    ]
];

$files = VideoSitemapWriter::write($pagesWithVideos, './sitemaps');
```

### News Sitemap

For news articles (last 2 days only).

```php
use IProDev\Sitemap\Sitemap\NewsSitemapWriter;

$newsPages = [
    [
        'url' => 'https://example.com/news/article',
        'title' => 'Breaking News',
        'publication_name' => 'Example News',
        'publication_date' => '2025-01-20T10:30:00Z',
        'language' => 'en',
        'keywords' => ['news', 'breaking', 'important']
    ]
];

$files = NewsSitemapWriter::write($newsPages, './sitemaps');
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --image-sitemap \
  --video-sitemap \
  --news-sitemap
```

---

## 5. Rate Limiting

### Overview
Control request rate to avoid being blocked.

### Features
- Requests per time window
- Per-domain throttling
- Delay between requests
- Respect Retry-After headers

### Usage

```php
use IProDev\Sitemap\RateLimiter;

$limiter = new RateLimiter(
    maxRequests: 100,          // Max requests
    timeWindow: 60,            // Per 60 seconds
    delayMs: 100,              // 100ms delay between requests
    respectRetryAfter: true    // Respect Retry-After headers
);

// Before each request
$limiter->throttle('example.com');

// Handle Retry-After
if ($retryAfter = $response->getHeader('Retry-After')) {
    $limiter->handleRetryAfter((int)$retryAfter[0]);
}

// Get statistics
$stats = $limiter->getStats();
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --rate-limit=100 \    # 100 requests per minute
  --delay=500           # 500ms delay
```

---

## 6. Scheduled Crawling

### Overview
Automate periodic crawls with cron integration.

### Usage

```php
use IProDev\Sitemap\Scheduler\CronScheduler;

$scheduler = new CronScheduler('./schedules.json');

// Add schedule
$scheduler->addSchedule('daily-crawl', [
    'url' => 'https://example.com',
    'schedule' => 'daily',  // or '0 2 * * *' (cron format)
    'out' => './sitemaps',
    'concurrency' => 20,
    'db_enabled' => true,
    'detect_changes' => true
]);

// Get due schedules
$due = $scheduler->getDueSchedules();

// Mark as run
$scheduler->markAsRun('daily-crawl');
```

### Cron Setup

```bash
# Add to crontab
* * * * * php /path/to/bin/scheduler >> /var/log/sitemap-scheduler.log 2>&1
```

### Schedule Formats

```php
'hourly'                // Every hour
'daily'                 // Every day at midnight
'weekly'                // Every Monday
'monthly'               // First day of month
'0 2 * * *'            // Every day at 2 AM
'0 */6 * * *'          // Every 6 hours
'0 0 * * 0'            // Every Sunday
```

---

## 7. SEO Analyzer

### Overview
Analyze pages for SEO issues and opportunities.

### Checks Performed

- ✅ Title tag (length, presence)
- ✅ Meta description (length, presence)
- ✅ Heading structure (H1, H2, etc.)
- ✅ Image alt attributes
- ✅ Internal/external links
- ✅ Canonical URL
- ✅ Meta robots tags
- ✅ Content length
- ✅ Keyword density
- ✅ Mobile optimization
- ✅ Page speed indicators
- ✅ Structured data

### Usage

```php
use IProDev\Sitemap\Analyzer\SeoAnalyzer;

$analyzer = new SeoAnalyzer();

$result = $analyzer->analyze($url, $html, $statusCode, $headers);

// Result structure
[
    'url' => 'https://example.com/page',
    'score' => 85,  // 0-100
    'issues' => [
        [
            'type' => 'title',
            'severity' => 'critical',
            'message' => 'Title too short'
        ]
    ],
    'warnings' => [
        [
            'type' => 'meta_description',
            'severity' => 'warning',
            'message' => 'Meta description could be longer'
        ]
    ],
    'recommendations' => [
        [
            'type' => 'headings',
            'message' => 'Consider adding H2 headings'
        ]
    ],
    'summary' => [
        'total_issues' => 1,
        'total_warnings' => 1,
        'total_recommendations' => 1
    ]
]
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --seo-analysis
```

---

## 8. Content Quality Checker

### Overview
Find content quality issues across your site.

### Checks

- Duplicate content
- Broken links
- Thin content (low word count)
- Missing meta descriptions
- Missing titles
- Slow pages
- Large pages
- Noindex pages

### Usage

```php
use IProDev\Sitemap\Analyzer\ContentQualityChecker;

$checker = new ContentQualityChecker();

// Find issues
$duplicates = $checker->findDuplicates($pages);
$brokenLinks = $checker->findBrokenLinks($pages);
$thinContent = $checker->findThinContent($pages, 300);
$slowPages = $checker->findSlowPages($pages, 3.0);

// Generate comprehensive report
$report = $checker->generateReport($pages);
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --check-quality \
  --find-duplicates \
  --find-broken-links
```

### Report Example

```json
{
  "total_pages": 1000,
  "duplicates": {
    "count": 5,
    "details": [...]
  },
  "broken_links": {
    "count": 12,
    "details": [...]
  },
  "thin_content": {
    "count": 23,
    "details": [...]
  },
  "quality_score": 78
}
```

---

## 9. Smart Filtering

### Overview
Control which URLs are crawled and their priorities.

### Features

- Include/exclude patterns
- Priority rules
- Glob-style matching

### Usage

```php
use IProDev\Sitemap\Filter\UrlFilter;

$filter = new UrlFilter([
    'exclude' => ['/admin/*', '/test/*', '*.pdf'],
    'include' => ['/products/*', '/blog/*'],
    'priority' => [
        'homepage' => 1.0,
        '/products/*' => 0.8,
        '/blog/*' => 0.6
    ]
]);

// Check single URL
if ($filter->shouldCrawl($url)) {
    // Crawl it
}

// Get priority
$priority = $filter->getPriority($url);

// Filter array of URLs
$filtered = $filter->filterUrls($urls);

// Filter pages with priorities
$pages = $filter->filterPages($pages);
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --exclude="/admin/*,/test/*,*.pdf" \
  --include="/products/*,/blog/*" \
  --priority-rules='{"homepage":1.0,"/products/*":0.8}'
```

---

## 10. Distributed Crawling

### Overview
Distribute crawling work across multiple workers.

### Usage

```php
use IProDev\Sitemap\WorkerPool;

$pool = new WorkerPool(5); // 5 workers

// Add tasks
foreach ($urls as $url) {
    $pool->addTask(function($data) {
        // Crawl URL
        return fetchAndParse($data['url']);
    }, ['url' => $url]);
}

// Process
$results = $pool->process();

// Statistics
$stats = $pool->getStats();
```

---

## 11. Resume Capability

### Overview
Resume interrupted crawls from checkpoint.

### Usage

```php
use IProDev\Sitemap\CrawlCheckpoint;

$checkpoint = new CrawlCheckpoint('./checkpoint.json', 1000);

// Save checkpoint
$checkpoint->save([
    'pages_crawled' => 5000,
    'queue' => $remainingUrls,
    'seen' => $seenUrls
]);

// Load checkpoint
if ($checkpoint->exists()) {
    $state = $checkpoint->load();
    // Resume from state
}

// Get info
$info = $checkpoint->getInfo();

// Delete
$checkpoint->delete();
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --resume \
  --checkpoint-interval=1000
```

---

## 12. Webhook Notifications

### Overview
Get notified about crawl events via webhooks.

### Events

- `crawl.started`
- `crawl.completed`
- `crawl.failed`
- `sitemap.generated`
- `changes.detected`

### Usage

```php
use IProDev\Sitemap\WebhookNotifier;

$webhooks = new WebhookNotifier();
$webhooks->addWebhook('https://example.com/webhook', ['crawl.*', 'sitemap.*']);

// Notify
$webhooks->notifyCrawlStarted($url, $config);
$webhooks->notifyCrawlCompleted($url, $stats);
$webhooks->notifyChangesDetected($changes);
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --webhook-url=https://example.com/webhook \
  --notify-on-complete \
  --notify-on-error
```

### Payload Example

```json
{
  "event": "crawl.completed",
  "timestamp": "2025-01-20T10:30:00Z",
  "data": {
    "url": "https://example.com",
    "stats": {
      "pages": 1523,
      "duration": 45.3,
      "errors": 0
    }
  }
}
```

---

## 13. Performance Metrics

### Overview
Track and analyze crawl performance.

### Metrics Collected

- Request count
- Response times
- Status code distribution
- Content sizes
- Memory usage
- Error rates

### Usage

```php
use IProDev\Sitemap\PerformanceMetrics;

$metrics = new PerformanceMetrics();

// Record metrics
$metrics->recordRequest($url, $statusCode, $duration, $size);
$metrics->recordError($url, $error, 'network');
$metrics->recordTiming('crawl_phase', $duration);
$metrics->recordMemory('checkpoint_1');

// Get statistics
$stats = $metrics->getStats();

// Generate reports
$textReport = $metrics->generateReport('text');
$jsonReport = $metrics->generateReport('json');

// Export to CSV
$metrics->exportToCsv('./metrics.csv');
```

---

## 14. Interactive Mode

### Overview
User-friendly CLI configuration wizard.

### Usage

```bash
php bin/sitemap --interactive
```

### Features

- Step-by-step configuration
- Input validation
- Default values
- Yes/No questions
- Advanced options
- Save configuration

---

## 15. Proxy Support

### Overview
Use HTTP/SOCKS proxies with rotation.

### Usage

```php
use IProDev\Sitemap\ProxyManager;

$manager = new ProxyManager([], true); // Enable rotation

// Add proxies
$manager->addProxy('http://proxy1.com:8080');
$manager->addProxy('http://proxy2.com:8080', 'user:pass');

// Get proxy
$proxy = $manager->getNextProxy();

// Test proxy
$result = $manager->testProxy('http://proxy.com:8080');

// Load from file
$manager = ProxyManager::loadFromFile('./proxies.txt');

// Get statistics
$stats = $manager->getStats();
```

### CLI

```bash
php bin/sitemap \
  --url=https://example.com \
  --proxy-file=./proxies.txt \
  --rotate-proxies
```

---

## 16. JavaScript Rendering

### Overview
Render JavaScript-heavy sites (SPAs) using headless Chrome.

### Requirements

- Chrome/Chromium installed
- PHP POSIX extension (optional)

### Usage

```php
use IProDev\Sitemap\HeadlessBrowser;
use IProDev\Sitemap\JavaScriptFetcher;

// Manual control
$browser = new HeadlessBrowser('/usr/bin/chromium', 9222);
$browser->start();

$result = $browser->render('https://spa.example.com', [
    'wait' => 2,
    'screenshot' => true
]);

// Automatic with fetcher
$fetcher = new JavaScriptFetcher([
    'enable_javascript' => true,
    'chrome_path' => '/usr/bin/chromium',
    'wait_for_ajax' => 5000
]);
```

### CLI

```bash
php bin/sitemap \
  --url=https://spa.example.com \
  --enable-javascript \
  --chrome-path=/usr/bin/chromium \
  --wait-for-ajax=5000
```

---

## 17. Environment Variables

The following environment variables can be used to configure the generator:

| Variable | Description | Default |
|----------|-------------|---------|
| `SITEMAP_CONCURRENCY` | Number of concurrent requests | `10` |
| `SITEMAP_TIMEOUT` | Request timeout in seconds | `30` |
| `SITEMAP_USER_AGENT` | Custom User-Agent string | Generator default |
| `SITEMAP_CACHE_DIR` | Cache directory path | `./cache` |
| `SITEMAP_CACHE_TTL` | Cache TTL in seconds | `3600` |
| `SITEMAP_DB_DSN` | Database connection string | `sqlite:./sitemap.db` |
| `SITEMAP_PROXY` | Proxy URL | None |
| `SITEMAP_CHROME_PATH` | Path to Chrome/Chromium | Auto-detect |
| `SITEMAP_LOG_LEVEL` | Log level (debug, info, warning, error) | `info` |

### Usage Example

```bash
export SITEMAP_CONCURRENCY=20
export SITEMAP_CACHE_DIR=/tmp/sitemap-cache
export SITEMAP_DB_DSN="mysql:host=localhost;dbname=sitemap"

php bin/sitemap --url=https://example.com
```

---

## 18. Complete Example

For a comprehensive example that demonstrates all features working together, see the [comprehensive.php](examples/comprehensive.php) file. This example includes:

- Cache initialization (File/Redis)
- Database setup and crawl tracking
- Rate limiting configuration
- URL filtering with priority rules
- Webhook notifications setup
- Performance metrics tracking
- Checkpoint/resume capability
- SEO analysis
- Content quality checking
- Change detection and reporting
- Multiple sitemap types generation

Run the comprehensive example:

```bash
php examples/comprehensive.php
```

---

## 🎓 Best Practices

1. **Start with defaults** and enable features gradually
2. **Use caching** for better performance
3. **Enable database** for change tracking
4. **Set rate limits** to avoid blocks
5. **Use filters** to reduce unnecessary crawling
6. **Monitor with metrics** and logs
7. **Test on small sites** first
8. **Use resume** for large sites
9. **Enable SEO analysis** for insights
10. **Setup webhooks** for monitoring

---

Made with ❤️ by iprodev
