# Quick Start Guide

Get up and running with PHP XML Sitemap Generator Pro in 5 minutes!

## 🚀 Installation

### Option 1: Composer (Recommended)

```bash
composer require iprodev/sitemap-generator-pro
```

### Option 2: Docker

```bash
docker pull iprodev/sitemap-generator-pro:latest
```

### Option 3: Manual

```bash
git clone https://github.com/iprodev/sitemap-generator-pro.git
cd sitemap-generator-pro
composer install
```

## 📝 Basic Usage

### 1. Simple Sitemap Generation

```bash
php bin/sitemap --url=https://www.example.com
```

**Result**: Creates `sitemap-1.xml.gz` and `sitemap-index.xml` in `./output/`

### 2. With Custom Output Directory

```bash
php bin/sitemap --url=https://www.example.com --out=./my-sitemaps
```

### 3. With Concurrency

```bash
php bin/sitemap --url=https://www.example.com --concurrency=20
```

### 4. Interactive Mode (Easiest!)

```bash
php bin/sitemap --interactive
```

Follow the wizard to configure all options.

## 🎯 Common Use Cases

### For Small Sites (< 1,000 pages)

```bash
php bin/sitemap \
  --url=https://example.com \
  --max-pages=1000 \
  --concurrency=5
```

### For Medium Sites (1,000 - 10,000 pages)

```bash
php bin/sitemap \
  --url=https://example.com \
  --max-pages=10000 \
  --concurrency=10 \
  --cache-enabled
```

### For Large Sites (> 10,000 pages)

```bash
php bin/sitemap \
  --url=https://example.com \
  --max-pages=50000 \
  --concurrency=20 \
  --cache-enabled \
  --resume \
  --checkpoint-interval=1000
```

### With SEO Analysis

```bash
php bin/sitemap \
  --url=https://example.com \
  --seo-analysis \
  --find-duplicates \
  --find-broken-links
```

### With Change Detection

```bash
# First run
php bin/sitemap \
  --url=https://example.com \
  --db-enabled

# Second run (detects changes)
php bin/sitemap \
  --url=https://example.com \
  --db-enabled \
  --detect-changes
```

### For JavaScript Sites (SPAs)

```bash
php bin/sitemap \
  --url=https://spa.example.com \
  --enable-javascript \
  --wait-for-ajax=5000
```

## 🐳 Docker Usage

### Quick Start

```bash
docker run --rm \
  -v $(pwd)/output:/app/output \
  iprodev/sitemap-generator-pro:latest \
  --url=https://example.com
```

### With Docker Compose

```bash
# Start services
docker-compose up -d

# Run sitemap generation
docker-compose run --rm app \
  --url=https://example.com \
  --db-enabled \
  --cache-enabled
```

## 💻 Programmatic Usage

### Basic Example

```php
<?php
require 'vendor/autoload.php';

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

$fetcher = new Fetcher(['concurrency' => 10]);
$robots = RobotsTxt::fromUrl('https://example.com', $fetcher);
$crawler = new Crawler($fetcher, $robots);

$pages = $crawler->crawl('https://example.com', 10000, 5);
$files = SitemapWriter::write($pages, './output');

echo "Generated " . count($files) . " files\n";
```

### With Caching

```php
use IProDev\Sitemap\Cache\FileCache;

$cache = new FileCache('./cache', 3600);
$fetcher = new Fetcher(['concurrency' => 10, 'cache' => $cache]);
// ... rest of the code
```

### With Database

```php
use IProDev\Sitemap\Database\Database;

$db = new Database('sqlite:./sitemap.db');
$db->createTables();

$crawlId = $db->startCrawl('example.com', 'https://example.com', []);

// After crawling
foreach ($pages as $page) {
    $db->saveUrl($crawlId, $page);
}

$db->completeCrawl($crawlId, ['total_pages' => count($pages)]);
```

## ⚙️ Configuration

### Using Config File

Create `sitemap.config.php`:

```php
<?php
return [
    'url' => 'https://example.com',
    'out' => './sitemaps',
    'concurrency' => 20,
    'max_pages' => 10000,
    'cache_enabled' => true,
    'db_enabled' => true,
];
```

Use it:

```bash
php bin/sitemap --config=sitemap.config.php
```

### Using Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
nano .env
```

## 📅 Scheduling (Automated Crawls)

### Setup Cron Job

```bash
# Run scheduler every minute
* * * * * cd /path/to/sitemap && php bin/scheduler >> logs/scheduler.log 2>&1
```

Or use Make:

```bash
make setup-cron
```

### Add Schedule

```php
use IProDev\Sitemap\Scheduler\CronScheduler;

$scheduler = new CronScheduler();
$scheduler->addSchedule('daily-crawl', [
    'url' => 'https://example.com',
    'schedule' => 'daily',  // or '0 2 * * *'
    'out' => './sitemaps',
]);
```

## 🔧 Troubleshooting

### Memory Issues

```bash
# Increase PHP memory limit
php -d memory_limit=512M bin/sitemap --url=https://example.com
```

Or in php.ini:
```ini
memory_limit = 512M
```

### Timeout Issues

```bash
# Increase max execution time
php -d max_execution_time=0 bin/sitemap --url=https://example.com
```

### Chrome Not Found (for JavaScript rendering)

```bash
# Specify Chrome path
php bin/sitemap \
  --url=https://example.com \
  --enable-javascript \
  --chrome-path=/usr/bin/chromium-browser
```

### Permission Issues

```bash
# Make directories writable
chmod -R 777 output cache logs
```

## 📊 Viewing Results

Generated files in `./output/`:
- `sitemap-1.xml.gz` - Compressed sitemap
- `sitemap-1.xml` - Uncompressed sitemap
- `sitemap-index.xml` - Index file

### Submit to Google

```bash
# Submit sitemap index to Google
curl "https://www.google.com/ping?sitemap=https://example.com/sitemap-index.xml"
```

## 🎓 Next Steps

1. Read the [Complete Documentation](README.md)
2. Explore [Advanced Features](FEATURES.md)
3. Check [Examples](examples/)
4. Join [Discussions](https://github.com/iprodev/sitemap-generator-pro/discussions)

## 💡 Tips

- Start with default settings
- Enable caching for better performance
- Use database for change tracking
- Set appropriate rate limits
- Use resume for large sites
- Enable SEO analysis for insights
- Setup webhooks for monitoring

## 🆘 Need Help?

- Check [FAQ](https://github.com/iprodev/sitemap-generator-pro/wiki/FAQ)
- Open an [Issue](https://github.com/iprodev/sitemap-generator-pro/issues)
- Join [Discussions](https://github.com/iprodev/sitemap-generator-pro/discussions)

---

Happy crawling! 🚀
