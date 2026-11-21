# PHP XML Sitemap Generator (Library + CLI)

A professional, production-ready PHP sitemap generator by **iProDev (Hemn Chawroka)** — supports concurrency, robots.txt, gzip compression, sitemap index files, and comprehensive error handling.

[![CI/CD Pipeline](https://github.com/iprodev/PHP-XML-Sitemap-Generator/actions/workflows/ci.yml/badge.svg)](https://github.com/iprodev/PHP-XML-Sitemap-Generator/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)
[![Version](https://img.shields.io/badge/version-3.0.0-orange.svg)](CHANGELOG.md)
[![codecov](https://codecov.io/gh/iprodev/PHP-XML-Sitemap-Generator/branch/main/graph/badge.svg)](https://codecov.io/gh/iprodev/PHP-XML-Sitemap-Generator)

--- 

## 🚀 What's New in v3.0

- ✨ **Database Storage** with change detection and historical tracking
- 🔄 **Resume Capability** with checkpoint system
- 🎯 **SEO Analysis** and content quality checking
- 📊 **Performance Metrics** and detailed analytics
- 🖼️ **Multi-format Sitemaps** (Images, Videos, News)
- 🌐 **JavaScript Rendering** support for SPAs
- 🔐 **Proxy Support** with rotation
- 🔔 **Webhook Notifications** for events
- 📅 **Scheduled Crawling** with cron integration
- 🎨 **Interactive Mode** for easy configuration
- ⚡ **Caching System** (File & Redis)
- 🎛️ **Smart Filtering** with priority rules
- 📈 **Rate Limiting** with retry handling

---

## 📋 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [CLI Usage](#-cli-usage)
- [Advanced Features](#-advanced-features)
- [Programmatic Usage](#-programmatic-usage)
- [Examples](#-examples)
- [Configuration](#-configuration)
- [API Reference](#-api-reference)
- [Testing](#-testing)
- [Docker](#-docker)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)

---

## ✨ Features

### Core Features
- 🚀 **High Performance** - Concurrent HTTP requests
- 🤖 **Robots.txt Compliant** - Respects crawling rules
- 📦 **Gzip Compression** - Automatic compression
- 📊 **Sitemap Index** - Multiple sitemap files
- 🛡️ **Error Handling** - Comprehensive error management
- 📝 **PSR-3 Logging** - Standard logging interface

### Advanced Features
- 💾 **Database Storage** - SQLite/MySQL/PostgreSQL support
- 🔄 **Change Detection** - Track URL changes over time
- 📈 **SEO Analysis** - Analyze pages for SEO issues
- 🔍 **Quality Checks** - Find duplicates, broken links
- 🎯 **Smart Filtering** - Include/exclude patterns
- ⚡ **Caching** - File and Redis cache support
- 📍 **Resume Support** - Continue interrupted crawls
- 🔔 **Webhooks** - Real-time notifications
- 📅 **Scheduling** - Automated periodic crawls
- 🌐 **JavaScript** - Render SPAs with headless Chrome
- 🔐 **Proxy Support** - HTTP/SOCKS proxies with rotation
- 🎨 **Interactive Mode** - User-friendly configuration

### Sitemap Types
- 📄 Standard XML Sitemap
- 🖼️ Image Sitemap
- 🎬 Video Sitemap
- 📰 News Sitemap

---

## 📥 Installation

```bash
composer require iprodev/sitemap-generator-pro
```

### Requirements
- PHP >= 8.0
- Extensions: curl, xml, mbstring, zlib, pdo
- Optional: redis, posix

---

## 🚀 Quick Start

### Basic Usage

```bash
php bin/sitemap --url=https://www.example.com
```

### Interactive Mode

```bash
php bin/sitemap --interactive
```

### With All Features

```bash
php bin/sitemap \
  --url=https://www.example.com \
  --out=./sitemaps \
  --concurrency=20 \
  --rate-limit=600 \
  --db-enabled \
  --seo-analysis \
  --image-sitemap \
  --webhook-url=https://example.com/webhook \
  --verbose
```

---

## 🖥️ CLI Usage

### Basic Options

```bash
--url=<URL>              # Starting URL (required)
--out=<PATH>             # Output directory
--concurrency=<N>        # Concurrent requests (1-100)
--max-pages=<N>          # Maximum pages to crawl
--max-depth=<N>          # Maximum link depth
--public-base=<URL>      # Public base URL for sitemap index (default: origin of --url)
--verbose, -v            # Verbose output
--help, -h               # Show help
```

### Crawling

```bash
--rate-limit=<N>           # Maximum requests per minute
--delay=<MS>               # Wait between requests (requests are sent one at a time)
--resume                   # Continue an interrupted crawl (Ctrl+C saves progress)
--checkpoint-interval=<N>  # Save progress every N requests (default: 1000, 0 = off)
--checkpoint-file=<PATH>   # Checkpoint file (default: ./checkpoint.json)
--max-response-size=<MB>   # Skip larger responses (default: 10, 0 = no limit)
--block-private-ips        # Refuse requests/redirects to private or internal addresses
```

Only indexable pages end up in the sitemap: redirects are followed and the final
URL is listed, `noindex` pages (meta robots or `X-Robots-Tag`) are left out, a page
whose canonical URL points elsewhere is replaced by its canonical URL, and
`rel="nofollow"` links are not followed.

### Database & Change Detection

```bash
--db-enabled             # Enable database storage
--db-dsn=<DSN>           # PDO DSN: sqlite:..., mysql:host=...;dbname=..., pgsql:host=...;dbname=...
--db-user=<USER>         # Database user (or $DB_USERNAME); the password is read from $DB_PASSWORD
--detect-changes         # Compare with previous crawl
--only-changed           # Only write new and modified URLs (needs --db-enabled)
```

### Filtering

```bash
--exclude=<PATTERNS>     # Never crawl or list matching URLs (comma-separated, * and ?)
--include=<PATTERNS>     # Only list matching URLs (other pages are still crawled to find links)
--priority-rules=<JSON>  # Priority rules as JSON, e.g. '{"/blog/*":0.8}'
```

### Reports

Reports are written as JSON files to `--report-dir` (default `./reports`):

```bash
--seo-analysis           # seo-report.json: score, issues and warnings per page
--check-quality          # quality-report.json: thin content, missing titles, slow pages, ...
--find-duplicates        # duplicates.json: pages with identical content
--find-broken-links      # broken-links.json: failing URLs and the page that links to them
```

### Advanced Sitemaps

All additional sitemaps are listed in `sitemap-index.xml`.

```bash
--image-sitemap            # Generate image sitemap
--video-sitemap            # Generate video sitemap (YouTube/Vimeo embeds, <video>)
--news-sitemap             # Generate news sitemap (articles from the last 2 days)
--news-publication=<NAME>  # Publication name (default: og:site_name or host)
```

### Proxy Support

```bash
--proxy=<URL>            # Proxy URL, e.g. http://user:pass@proxy:8080
--proxy-file=<PATH>      # Load proxies from file
--rotate-proxies         # Use the next proxy for every request
```

### Webhooks

```bash
--webhook-url=<URL>      # Webhook for notifications (passwords and tokens in the config are redacted)
--notify-on-complete     # Notify when complete
--notify-on-error        # Notify on errors
```

### Not functional yet

`--enable-javascript`, `--chrome-path` and `--wait-for-ajax` (JavaScript rendering) and
`--cache-enabled`, `--cache-driver` and `--cache-ttl` are accepted but have no effect;
the tool prints a warning when they are used.

---

## 🎯 Advanced Features

### 1. Database Storage & Change Detection

Track changes over time:

```bash
php bin/sitemap \
  --url=https://example.com \
  --db-enabled \
  --detect-changes
```

The system will:
- Store all URLs in database
- Compare with previous crawl
- Generate change report (new, modified, deleted)
- Track SEO metrics over time

### 2. Resume Interrupted Crawls

Large crawls can be resumed:

```bash
php bin/sitemap \
  --url=https://example.com \
  --resume \
  --checkpoint-interval=1000
```

### 3. SEO Analysis

Analyze pages for SEO issues:

```bash
php bin/sitemap \
  --url=https://example.com \
  --seo-analysis \
  --find-duplicates \
  --find-broken-links
```

Reports include:
- Missing title/meta descriptions
- Duplicate content
- Broken links
- Page load times
- Mobile optimization
- Structured data

### 4. JavaScript Rendering

> **Not functional yet.** The options below are accepted but pages are fetched without
> running JavaScript.

For SPAs (React, Vue, Angular):

```bash
php bin/sitemap \
  --url=https://spa.example.com \
  --enable-javascript \
  --chrome-path=/usr/bin/chromium \
  --wait-for-ajax=5000
```

### 5. Scheduled Crawling

Setup automated crawls:

```php
use IProDev\Sitemap\Scheduler\CronScheduler;

$scheduler = new CronScheduler();
$scheduler->addSchedule('daily-crawl', [
    'url' => 'https://example.com',
    'schedule' => 'daily',  // or cron: '0 2 * * *'
    'out' => './sitemaps',
    'db_enabled' => true
]);

// Add to crontab:
// * * * * * cd /path/to/project && php bin/scheduler ./schedules.json
```

`schedule` accepts any 5-field cron expression (`*/15 * * * *`, `30 2 * * 1-5`,
`0 0 1 jan,jul *`, ...) or `hourly`, `daily`, `weekly` (Monday), `monthly`, `yearly`.
Config keys may be written like the CLI options (`max-pages`), in snake_case
(`max_pages`) or camelCase (`maxPages`); unknown keys are ignored.

Only one scheduler process runs at a time, and a schedule's next run is set before
its crawl starts, so a crawl that is slow or fails is not started again every minute.

### 6. Webhooks

Get notified of events:

```bash
php bin/sitemap \
  --url=https://example.com \
  --webhook-url=https://example.com/webhook \
  --notify-on-complete \
  --notify-on-error
```

Webhook payload:
```json
{
  "event": "crawl.completed",
  "timestamp": "2025-01-20T10:30:00Z",
  "data": {
    "url": "https://example.com",
    "stats": {
      "pages": 1523,
      "duration": 45.3
    }
  }
}
```

### 7. Proxy Support

Use proxies for crawling:

```bash
# Single proxy
php bin/sitemap \
  --url=https://example.com \
  --proxy=http://proxy.example.com:8080

# Proxy file with rotation
php bin/sitemap \
  --url=https://example.com \
  --proxy-file=./proxies.txt \
  --rotate-proxies
```

Proxy file format:
```
http://proxy1.example.com:8080
http://proxy2.example.com:8080|username:password
socks5://proxy3.example.com:1080
```

### 8. Smart Filtering

Control what gets crawled:

```bash
php bin/sitemap \
  --url=https://example.com \
  --exclude="/admin/*,/test/*,*.pdf" \
  --include="/products/*,/blog/*" \
  --priority-rules='{"homepage":1.0,"/products/*":0.8}'
```

---

## 💻 Programmatic Usage

### Basic Example

```php
use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

$fetcher = new Fetcher(['concurrency' => 20]);
$robots = RobotsTxt::fromUrl('https://example.com', $fetcher);
$crawler = new Crawler($fetcher, $robots);

$pages = $crawler->crawl('https://example.com', 10000, 5);
$files = SitemapWriter::write($pages, './sitemaps');
```

### With Database & Change Detection

```php
use IProDev\Sitemap\Database\Database;
use IProDev\Sitemap\ChangeDetector;

// Initialize database
$db = new Database('sqlite:./sitemap.db');
$db->createTables();

// Start crawl
$domain = 'example.com';
$crawlId = $db->startCrawl($domain, 'https://example.com', []);

// Crawl and save
foreach ($pages as $page) {
    $db->saveUrl($crawlId, $page);
}

// Detect changes
$prevCrawl = $db->getPreviousCrawl($domain, $crawlId);
if ($prevCrawl) {
    $detector = new ChangeDetector($db);
    $changes = $detector->detectChanges($prevCrawl['id'], $crawlId);
    
    print_r($changes);
}
```

### With SEO Analysis

```php
use IProDev\Sitemap\Analyzer\SeoAnalyzer;

$analyzer = new SeoAnalyzer();

foreach ($pages as $page) {
    $analysis = $analyzer->analyze(
        $page['url'], 
        $page['html'], 
        $page['status_code']
    );
    
    echo "Score: {$analysis['score']}/100\n";
    echo "Issues: " . count($analysis['issues']) . "\n";
}
```

### With Caching

```php
use IProDev\Sitemap\Cache\FileCache;
use IProDev\Sitemap\Cache\RedisCache;

// File cache
$cache = new FileCache('./cache', 3600);

// Redis cache
$cache = new RedisCache('127.0.0.1', 6379);

// Use in fetcher
$fetcher = new Fetcher(['cache' => $cache]);
```

---

## ⚙️ Configuration

### Configuration File

Create `sitemap.config.php`:

```php
<?php

return [
    'url' => 'https://example.com',
    'out' => './sitemaps',
    'concurrency' => 20,
    'max_pages' => 10000,
    'max_depth' => 5,
    'cache_enabled' => true,
    'db_enabled' => true,
    'seo_analysis' => true,
    'exclude' => ['/admin/*', '/test/*'],
    'priority_rules' => [
        'homepage' => 1.0,
        '/products/*' => 0.8,
        '/blog/*' => 0.6
    ]
];
```

Use config file:

```bash
php bin/sitemap --config=sitemap.config.php
```

---

## 📂 Examples

The `examples/` directory contains ready-to-use code examples:

### Basic Example (`examples/basic.php`)
Simple sitemap generation with minimal configuration:

```php
require 'vendor/autoload.php';

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

$fetcher = new Fetcher(['concurrency' => 10]);
$robots = RobotsTxt::fromUrl('https://example.com', $fetcher);
$crawler = new Crawler($fetcher, $robots);

$pages = $crawler->crawl('https://example.com', 1000, 3);
SitemapWriter::write($pages, './output');
```

### Advanced Example (`examples/advanced.php`)
Includes caching, filtering, and rate limiting.

### Comprehensive Example (`examples/comprehensive.php`)
Full-featured example demonstrating all capabilities:
- Database storage with change detection
- SEO analysis and quality checks
- Performance metrics
- Webhook notifications
- Image sitemap generation
- Resume capability

Run the comprehensive example:
```bash
php examples/comprehensive.php
```

---

## 📖 API Reference

### Core Classes

| Class | Description |
|-------|-------------|
| `Fetcher` | HTTP client with concurrent request support |
| `Crawler` | Web crawler with depth and page limits |
| `Parser` | HTML parser for link extraction |
| `RobotsTxt` | Robots.txt parser with wildcard support |
| `SitemapWriter` | XML sitemap generator with gzip compression |
| `Utils` | Utility functions (URL handling, formatting) |

### Advanced Classes

| Class | Description |
|-------|-------------|
| `Database` | SQLite/MySQL/PostgreSQL storage |
| `ChangeDetector` | Detect URL changes between crawls |
| `SeoAnalyzer` | Page-level SEO analysis |
| `ContentQualityChecker` | Find duplicates, broken links, thin content |
| `UrlFilter` | Include/exclude patterns with priority rules |
| `FileCache` / `RedisCache` | Caching implementations |
| `RateLimiter` | Request throttling |
| `CrawlCheckpoint` | Resume interrupted crawls |
| `WebhookNotifier` | Event notifications |
| `ProxyManager` | Proxy rotation support |
| `PerformanceMetrics` | Crawl statistics and reporting |

### Sitemap Writers

| Class | Description |
|-------|-------------|
| `SitemapWriter` | Standard XML sitemap |
| `ImageSitemapWriter` | Image sitemap with title/caption |
| `VideoSitemapWriter` | Video sitemap with metadata |
| `NewsSitemapWriter` | News sitemap for Google News |

---

## 🧪 Testing

```bash
# Run tests
composer test

# With coverage
composer test-coverage

# Code style
composer lint

# Static analysis
composer analyze

# All checks
composer check
```

---

## 🐳 Docker

```bash
# Build
docker build -t sitemap-generator-pro .

# Run
docker run --rm \
  -v "$(pwd):/data" \
  --user "$(id -u):$(id -g)" \
  sitemap-generator-pro \
  --url=https://example.com \
  --out=./sitemaps
```

---

## 📊 Performance Tips

1. **Increase Concurrency**: For faster crawling
   ```bash
   --concurrency=50
   ```

2. **Enable Caching**: Reduce duplicate requests
   ```bash
   --cache-enabled --cache-driver=redis
   ```

3. **Use Database**: Track changes efficiently
   ```bash
   --db-enabled --detect-changes
   ```

4. **Smart Filtering**: Reduce unnecessary pages
   ```bash
   --exclude="/admin/*,*.pdf"
   ```

5. **Resume Support**: Handle large sites
   ```bash
   --resume --checkpoint-interval=1000
   ```

---

## 🔧 Troubleshooting

### Common Issues

**1. Memory Exhausted Error**
```bash
# Increase PHP memory limit
php -d memory_limit=512M bin/sitemap --url=https://example.com
```

**2. Too Many Open Connections**
```bash
# Reduce concurrency
php bin/sitemap --url=https://example.com --concurrency=5
```

**3. Crawl Takes Too Long**
```bash
# Use checkpoints and resume
php bin/sitemap --url=https://example.com --resume --checkpoint-interval=500
```

**4. Rate Limited by Server**
```bash
# Enable rate limiting
php bin/sitemap --url=https://example.com --rate-limit=30 --delay=1000
```

**5. JavaScript-rendered Content Not Captured**
```bash
# Enable JavaScript rendering (requires Chrome/Chromium)
php bin/sitemap --url=https://example.com --enable-javascript --chrome-path=/usr/bin/chromium
```

**6. SSL Certificate Errors**
```php
// In code, configure Guzzle to skip verification (not recommended for production)
$fetcher = new Fetcher(['verify' => false]);
```

### Debug Mode

Enable verbose output for debugging:
```bash
php bin/sitemap --url=https://example.com --verbose
```

### Logs

When using database storage, check crawl history:
```bash
sqlite3 sitemap.db "SELECT * FROM crawls ORDER BY created_at DESC LIMIT 5;"
```

---

## 🔒 Security

- Path traversal prevention
- URL validation and sanitization
- Safe XML generation
- Proxy authentication support
- Rate limiting to prevent blocking

---

## 📝 License

MIT License - see [LICENSE.md](LICENSE.md)

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`composer check`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/iprodev/PHP-XML-Sitemap-Generator.git
cd sitemap-generator-pro

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer lint

# Fix code style issues
composer phpcbf

# Run static analysis
composer analyze
```

---

## 🙏 Credits

Created by **iProDev (Hemn Chawroka)** - [https://github.com/iprodev](https://github.com/iprodev)

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/iprodev/PHP-XML-Sitemap-Generator/issues)
- **Discussions**: [GitHub Discussions](https://github.com/iprodev/PHP-XML-Sitemap-Generator/discussions)
- **Documentation**: [Wiki](https://github.com/iprodev/PHP-XML-Sitemap-Generator/wiki)

---

Made with ❤️ by iProDev (Hemn Chawroka)
