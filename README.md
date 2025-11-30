# PHP XML Sitemap Generator (Library + CLI)

A professional, production-ready PHP sitemap generator by **iProDev (Hemn Chawroka)** — supports concurrency, robots.txt, gzip compression, sitemap index files, and comprehensive error handling.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)
[![Version](https://img.shields.io/badge/version-3.0.0-orange.svg)](CHANGELOG.md)

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
- [Configuration](#-configuration)
- [Testing](#-testing)
- [Docker](#-docker)
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
  --cache-enabled \
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
--public-base=<URL>      # Public base URL for sitemap index
--verbose, -v            # Verbose output
--help, -h               # Show help
```

### Caching

```bash
--cache-enabled          # Enable caching
--cache-driver=file      # Cache driver: file|redis
--cache-ttl=3600         # Cache TTL in seconds
```

### Database & Change Detection

```bash
--db-enabled             # Enable database storage
--db-dsn=<DSN>           # Database DSN
--detect-changes         # Compare with previous crawl
--only-changed           # Only include changed URLs
```

### Resume Support

```bash
--resume                 # Resume from checkpoint
--checkpoint-interval=<N> # Save checkpoint every N pages
```

### Rate Limiting

```bash
--rate-limit=<N>         # Requests per minute
--delay=<MS>             # Delay between requests (ms)
```

### Filtering

```bash
--exclude=<PATTERNS>     # Exclude patterns (comma-separated)
--include=<PATTERNS>     # Include only patterns
--priority-rules=<JSON>  # Priority rules as JSON
```

### SEO & Analysis

```bash
--seo-analysis           # Enable SEO analysis
--check-quality          # Check content quality
--find-duplicates        # Find duplicate content
--find-broken-links      # Find broken links
```

### Advanced Sitemaps

```bash
--image-sitemap          # Generate image sitemap
--video-sitemap          # Generate video sitemap
--news-sitemap           # Generate news sitemap
```

### JavaScript Rendering

```bash
--enable-javascript      # Enable JS rendering
--chrome-path=<PATH>     # Path to Chrome/Chromium
--wait-for-ajax=<MS>     # Wait time for AJAX
```

### Proxy Support

```bash
--proxy=<URL>            # Proxy URL
--proxy-file=<PATH>      # Load proxies from file
--rotate-proxies         # Rotate through proxies
```

### Webhooks

```bash
--webhook-url=<URL>      # Webhook for notifications
--notify-on-complete     # Notify when complete
--notify-on-error        # Notify on errors
```

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
// * * * * * php bin/scheduler
```

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
  -v $(pwd)/sitemaps:/app/output \
  sitemap-generator-pro \
  --url=https://example.com \
  --out=/app/output
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

## 🙏 Credits

Created by **iProDev (Hemn Chawroka)** - [https://github.com/iprodev](https://github.com/iprodev)

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/iprodev/sitemap-generator-pro/issues)
- **Discussions**: [GitHub Discussions](https://github.com/iprodev/sitemap-generator-pro/discussions)
- **Documentation**: [Wiki](https://github.com/iprodev/sitemap-generator-pro/wiki)

---

Made with ❤️ by iProDev (Hemn Chawroka)
