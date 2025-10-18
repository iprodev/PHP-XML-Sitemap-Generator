# PHP XML Sitemap Generator (Library + CLI)

A professional, production-ready PHP sitemap generator by **IProDev (Hemn Chawroka)** — supports concurrency, robots.txt, gzip compression, sitemap index files, and comprehensive error handling.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)

## ✨ Features

- 🚀 **High Performance**: Concurrent HTTP requests using Guzzle
- 🤖 **Robots.txt Support**: Respects robots.txt rules (including wildcards)
- 📦 **Gzip Compression**: Automatic .gz file generation
- 📊 **Sitemap Index**: Automatic index file creation for large sites
- 🛡️ **Error Handling**: Comprehensive error handling and validation
- 📝 **Logging**: PSR-3 compatible logging support
- 🎯 **Canonical URLs**: Automatic canonical URL detection
- 🧪 **Well Tested**: Comprehensive unit tests with PHPUnit
- 🐳 **Docker Support**: Ready-to-use Docker configuration
- 💻 **CLI Tool**: Professional command-line interface with progress reporting

## 📋 Requirements

- PHP >= 8.0
- Composer
- Extensions: `curl`, `xml`, `mbstring`, `zlib`

## 📥 Installation

```bash
composer require iprodev/sitemap-generator-pro
```

## 🚀 CLI Usage

### Basic Usage

```bash
php bin/sitemap --url=https://www.example.com
```

### Advanced Usage

```bash
php bin/sitemap \
  --url=https://www.iprodev.com \
  --out=./sitemaps \
  --concurrency=20 \
  --max-pages=10000 \
  --max-depth=5 \
  --public-base=https://www.iprodev.com \
  --verbose
```

### CLI Options

| Option | Required | Default | Description |
|--------|----------|---------|-------------|
| `--url` | ✅ Yes | - | Starting URL to crawl |
| `--out` | No | `./output` | Output directory for sitemap files |
| `--concurrency` | No | `10` | Number of concurrent HTTP requests (1-100) |
| `--max-pages` | No | `50000` | Maximum number of pages to crawl |
| `--max-depth` | No | `5` | Maximum link depth to follow |
| `--public-base` | No | - | Public base URL for sitemap index |
| `--verbose`, `-v` | No | `false` | Enable verbose output |
| `--help`, `-h` | No | - | Show help message |

### CLI Output Example

```
======================================================================
  PHP XML Sitemap Generator
======================================================================
Configuration:
  URL:         https://www.example.com
  Domain:      www.example.com
  Output:      ./output
  Concurrency: 20
  Max Pages:   10000
  Max Depth:   5
======================================================================

[0.50s] [info] Initializing crawler...
[0.75s] [info] Fetching robots.txt...
[1.20s] [info] Starting crawl...
[45.30s] [info] Crawl completed {"duration":"45.3s","pages":1523}

======================================================================
  ✅ Success!
======================================================================
Generated Files:
  • sitemap-1.xml.gz (125.4 KB)
  • sitemap-index.xml (892 B)

Statistics:
  • Total Pages:    1523
  • Total Time:     46.2s
  • Crawl Speed:    33.0 pages/sec
  • Memory Used:    45.8 MB
  • Output Dir:     ./output
======================================================================
```

## 💻 Programmatic Usage

### Basic Example

```php
use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

// Initialize fetcher
$fetcher = new Fetcher(['concurrency' => 10]);

// Load robots.txt
$robots = RobotsTxt::fromUrl('https://www.example.com', $fetcher);

// Create crawler
$crawler = new Crawler($fetcher, $robots);

// Crawl website
$pages = $crawler->crawl('https://www.example.com', 10000, 5);

// Write sitemap files
$files = SitemapWriter::write(
    $pages, 
    __DIR__ . '/sitemaps', 
    50000, 
    'https://www.example.com'
);

echo "Generated " . count($files) . " files\n";
```

### Advanced Example with Logging

```php
use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger
$logger = new Logger('sitemap');
$logger->pushHandler(new StreamHandler('sitemap.log', Logger::INFO));

// Initialize with logger
$fetcher = new Fetcher([
    'concurrency' => 20,
    'timeout' => 15,
], $logger);

$robots = RobotsTxt::fromUrl('https://www.example.com', $fetcher);
$crawler = new Crawler($fetcher, $robots, $logger);

// Crawl with error handling
try {
    $pages = $crawler->crawl('https://www.example.com', 10000, 5);
    $files = SitemapWriter::write($pages, './sitemaps', 50000, 'https://www.example.com');
    
    // Get statistics
    $stats = $crawler->getStats();
    echo "Processed: {$stats['processed']} pages\n";
    echo "Unique URLs: {$stats['unique_urls']}\n";
    
} catch (\InvalidArgumentException $e) {
    echo "Configuration error: {$e->getMessage()}\n";
} catch (\RuntimeException $e) {
    echo "Runtime error: {$e->getMessage()}\n";
}
```

### Custom Fetcher Configuration

```php
$fetcher = new Fetcher([
    'concurrency' => 20,
    'timeout' => 15,
    'connect_timeout' => 10,
    'headers' => [
        'User-Agent' => 'MyBot/1.0',
    ],
    'verify' => true, // SSL verification
], $logger);
```

## 🧪 Testing

Run unit tests:

```bash
composer install
vendor/bin/phpunit
```

Run with coverage:

```bash
vendor/bin/phpunit --coverage-html coverage
```

Code style check:

```bash
vendor/bin/phpcs --standard=PSR12 src/ tests/
```

## 🐳 Docker Usage

Build the Docker image:

```bash
docker build -t sitemap-generator-pro .
```

Run the container:

```bash
docker run --rm \
  -v $(pwd)/sitemaps:/app/output \
  sitemap-generator-pro \
  --url=https://www.iprodev.com \
  --out=/app/output \
  --concurrency=20 \
  --max-pages=10000 \
  --public-base=https://www.iprodev.com \
  --verbose
```

## 📚 API Documentation

### Fetcher

```php
// Constructor
new Fetcher(array $options = [], ?LoggerInterface $logger = null)

// Fetch multiple URLs concurrently
fetchMany(array $urls, callable $onFulfilled, ?callable $onRejected = null): void

// Fetch single URL
get(string $url): ResponseInterface

// Get concurrency setting
getConcurrency(): int
```

### Crawler

```php
// Constructor
new Crawler(Fetcher $fetcher, RobotsTxt $robots, ?LoggerInterface $logger = null)

// Crawl website
crawl(string $startUrl, int $maxPages = 10000, int $maxDepth = 5): array

// Get crawl statistics
getStats(): array
```

### SitemapWriter

```php
// Write sitemap files
static write(
    array $pages, 
    string $outPath, 
    int $maxPerFile = 50000, 
    ?string $publicBase = null
): array
```

### Parser

```php
// Extract links from HTML
static extractLinks(string $html, string $baseUrl): array

// Resolve relative URL
static resolveUrl(string $href, string $base): ?string

// Get canonical URL
static getCanonical(string $html, string $baseUrl): ?string

// Get meta robots directives
static getMetaRobots(string $html): array
```

### RobotsTxt

```php
// Load from URL
static fromUrl(string $baseUrl, Fetcher $fetcher): RobotsTxt

// Check if URL is allowed
isAllowed(string $url): bool

// Get disallow rules
getDisallows(): array

// Get allow rules
getAllows(): array
```

### Utils

```php
static normalizeUrl(string $url): string
static formatBytes(int $bytes, int $precision = 2): string
static formatDuration(float $seconds): string
static isValidUrl(string $url): bool
static getDomain(string $url): ?string
static calculateProgress(int $current, int $total): float
static progressBar(int $current, int $total, int $width = 50): string
static getMemoryUsage(): string
static getPeakMemoryUsage(): string
static cleanUrl(string $url, bool $removeQuery = false): string
```

## 🔧 Configuration Best Practices

### For Small Sites (< 1,000 pages)

```bash
--concurrency=5 --max-pages=1000 --max-depth=10
```

### For Medium Sites (1,000 - 10,000 pages)

```bash
--concurrency=10 --max-pages=10000 --max-depth=5
```

### For Large Sites (> 10,000 pages)

```bash
--concurrency=20 --max-pages=50000 --max-depth=3
```

## 🛡️ Error Handling

The library includes comprehensive error handling:

- **Invalid URLs**: Validates all URLs before processing
- **Network Errors**: Gracefully handles timeouts and connection failures
- **Memory Management**: Efficient memory usage for large sites
- **File System Errors**: Proper validation and error messages
- **Robots.txt Parsing**: Handles malformed robots.txt files

## 📝 Generated Files

The generator creates the following files:

- `sitemap-1.xml` - First sitemap file
- `sitemap-1.xml.gz` - Compressed version
- `sitemap-2.xml.gz` - Additional files if needed
- `sitemap-index.xml` - Index file listing all sitemaps

## 🔒 Security Considerations

- Path traversal prevention
- URL validation and sanitization
- Safe XML generation with proper escaping
- Robots.txt respect
- Meta robots tag support
- SSL certificate verification

## 📊 Performance Tips

1. **Increase Concurrency**: For faster crawling (up to 100)
2. **Reduce Max Depth**: Focus on important pages
3. **Use Memory**: Ensure adequate memory for large sites
4. **Network**: Fast and stable internet connection recommended
5. **Robots.txt**: Proper robots.txt reduces unnecessary requests

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Follow PSR-12 coding standards
5. Submit a pull request

## 📄 License

MIT License - see [LICENSE.md](LICENSE.md) for details

## 🙏 Credits

Created by **iprodev** - [https://github.com/iprodev](https://github.com/iprodev)

## 📞 Support

- Issues: [GitHub Issues](https://github.com/iprodev/sitemap-generator-pro/issues)
- Discussions: [GitHub Discussions](https://github.com/iprodev/sitemap-generator-pro/discussions)

---

Made with ❤️ by iprodev
