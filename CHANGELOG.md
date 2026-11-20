# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### 🔒 Security

- **Cache:** `FileCache` and `RedisCache` store values as JSON instead of PHP `serialize()`, so a tampered cache file or Redis key can no longer instantiate PHP objects (insecure deserialization). Cached values must now be JSON-serializable; `set()` returns `false` otherwise. Entries written by older versions are treated as cache misses.
- **Scheduler:** `bin/scheduler` runs `bin/sitemap` through `proc_open()` with an argument array instead of a shell string, and only passes known options (`CronScheduler::buildArguments()`). Schedule config values or keys can no longer inject shell commands.
- **Secrets:** the crawl configuration sent in the `crawl.started` webhook and stored in the `sitemap_crawls` table is redacted (`Utils::redactConfig()`): proxy and DSN passwords, webhook tokens and secret-looking keys are masked. The proxy shown in the CLI summary and the scheduler's command log are redacted too.
- **Proxy credentials** are embedded in the proxy URL instead of Guzzle's `auth` option, which sent them as an `Authorization` header to every crawled site.
- **HTML change report:** crawled URLs are HTML-escaped (stored XSS).
- **docker-compose:** all ports are bound to `127.0.0.1`; MySQL, PostgreSQL, Redis and browserless Chrome require passwords/tokens from `.env`; phpMyAdmin no longer logs in automatically as root; the admin UIs only start with `--profile tools`.

### 🐛 Fixed

- **Sitemap XML:** URLs and texts were escaped twice (`&` became `&amp;amp;`) in the URL, image, video and news sitemaps.
- **Sitemap index:** `<loc>` entries are always absolute; without `--public-base` the origin of the crawled site is used. `<lastmod>` now carries the real UTC offset instead of a hard-coded `+00:00`.
- **robots.txt** follows RFC 9309: consecutive `User-agent` lines share one group, the most specific (longest) rule wins with `Allow` winning ties, and only the groups for this crawler (`php-sitemap-generator`) or, failing that, `*` apply. Added `RobotsTxt::fromString()`.
- **Proxy:** `--proxy` / `--proxy-file` were applied after the HTTP client had been created and were silently ignored. `--proxy` and `--proxy-file` can now be combined and `--rotate-proxies` is passed to proxies loaded from a file.
- **Scheduler:** camelCase and snake_case config keys (`maxPages`, `max_pages`) are mapped to the matching CLI options.
- **CI:** runs on `master`; Docker image name is lowercase; the release job no longer depends on the push-only Docker job; coverage is written to `coverage.xml` for Codecov.

## [3.0.0] - 2025-11-30

### 🎉 Major Release

This release brings enterprise-grade features including caching, database storage, SEO analysis, JavaScript rendering, and much more.

### ✨ Added

- **Cache System**
  - File-based caching for local storage
  - Redis cache driver for distributed caching
  - PSR-16 compatible interface
  - Configurable TTL and cache statistics
  - 30-50% faster repeated crawls

- **Database Storage**
  - SQLite support (default)
  - MySQL/PostgreSQL support
  - Historical crawl tracking
  - URL metadata storage (title, meta description, word count, etc.)
  - Comprehensive schema with migrations

- **Change Detection**
  - Detect new, modified, and deleted URLs between crawls
  - Content hash comparison
  - Text/HTML/JSON report generation
  - `--only-changed` option for incremental sitemaps

- **Specialized Sitemap Types**
  - Image Sitemap with title/caption support
  - Video Sitemap with duration/thumbnail/family-friendly flags
  - News Sitemap for Google News (publication name, language, keywords)

- **Rate Limiting**
  - Configurable requests per time window
  - Per-domain throttling
  - Delay between requests
  - Retry-After header support

- **Scheduled Crawling**
  - Cron-based scheduler (`bin/scheduler`)
  - Predefined schedules (hourly, daily, weekly, monthly)
  - Custom cron expressions
  - Schedule persistence with JSON storage

- **SEO Analyzer**
  - Title tag analysis (length, presence)
  - Meta description analysis
  - Heading structure validation (H1, H2, etc.)
  - Image alt attribute checking
  - Internal/external link analysis
  - SEO score calculation (0-100)
  - Issue severity levels (critical, warning, info)

- **Content Quality Checker**
  - Duplicate content detection
  - Broken link finder
  - Thin content detection (configurable word count)
  - Slow page detection
  - Quality score calculation

- **Smart URL Filtering**
  - Include/exclude patterns with glob support
  - Priority rules per URL pattern
  - Configurable via CLI or code

- **Distributed Crawling**
  - Worker pool for parallel processing
  - Configurable worker count
  - Task distribution and result aggregation

- **Resume Capability**
  - Checkpoint-based crawl resumption
  - Configurable checkpoint intervals
  - Automatic state persistence
  - Graceful interrupt handling

- **Webhook Notifications**
  - Event-based notifications (crawl.started, crawl.completed, crawl.failed)
  - Sitemap generation events
  - Change detection events
  - JSON payload with full statistics

- **Performance Metrics**
  - Request count and timing
  - Response time statistics
  - Status code distribution
  - Memory usage tracking
  - Text/JSON/CSV export

- **Interactive Mode**
  - Step-by-step configuration wizard
  - Input validation
  - Default value suggestions
  - Configuration saving

- **Proxy Support**
  - HTTP/SOCKS proxy support
  - Proxy rotation
  - Authentication support
  - Proxy file loading
  - Health checking

- **JavaScript Rendering**
  - Headless Chrome/Chromium integration
  - SPA and dynamic content support
  - Configurable wait times
  - Screenshot capability
  - Automatic browser management

### 📁 New Files

- `bin/scheduler` - Cron scheduler command
- `docker-compose.yml` - Docker Compose configuration
- `Makefile` - Build and development commands
- `.env.example` - Environment configuration template
- `QUICKSTART.md` - Quick start guide
- `CONTRIBUTING.md` - Contribution guidelines
- `FEATURES.md` - Complete feature documentation
- `examples/comprehensive.php` - Full feature example

### 🔧 Changed

- Updated `composer.json` with new dependencies
- Enhanced CLI with 50+ new options
- Improved Docker support with multi-stage builds
- Better CI/CD pipeline configuration

### ⚠️ Breaking Changes

- Minimum PHP version remains 8.0
- New required extension: `ext-pdo` for database support
- Some CLI options renamed for consistency
- Crawler constructor accepts additional optional parameters

### 📚 Documentation

- Complete FEATURES.md with all features documented
- QUICKSTART.md for rapid onboarding
- CONTRIBUTING.md for contributors
- Updated README with new features
- Comprehensive code examples

---

## [2.0.1] - 2025-10-18

### 🎉 Major Improvements

This release represents a complete overhaul of the codebase with numerous bug fixes, improvements, and new features.

### ✨ Added

- **Comprehensive Error Handling**
  - Try-catch blocks throughout all classes
  - Proper exception types (`InvalidArgumentException`, `RuntimeException`)
  - Detailed error messages with context
  - Graceful degradation for non-critical errors

- **PSR-3 Logging Support**
  - Full PSR-3 compatible logging
  - Console logger with color-coded output
  - Verbose mode for debugging
  - Progress tracking and statistics

- **Enhanced CLI Interface**
  - Professional help messages
  - Input validation and sanitization
  - Progress indicators
  - Detailed output with statistics
  - Memory and time tracking
  - Color-coded log levels

- **Input Validation**
  - URL format validation
  - Numeric parameter bounds checking
  - Path sanitization
  - File system permission checks

- **Improved Parser**
  - Better HTML parsing with encoding handling
  - Support for malformed HTML
  - Meta robots tag detection
  - Multiple canonical URL formats
  - Improved link extraction

- **Enhanced RobotsTxt**
  - Wildcard pattern matching (`*` and `$`)
  - Allow directive support
  - Better rule precedence handling
  - Port-aware URL parsing
  - Comprehensive rule testing

- **Better SitemapWriter**
  - XML schema validation
  - Proper XML escaping
  - Date format validation
  - File size checking
  - Atomic file operations
  - Better index file generation

- **New Utils Class**
  - Byte formatting
  - Duration formatting
  - Progress bar generation
  - Memory usage reporting
  - URL cleaning and normalization

- **Comprehensive Tests**
  - ParserTest with 10+ test cases
  - RobotsTxtTest with wildcard testing
  - UtilsTest for utility functions
  - Edge case coverage
  - Malformed input handling

### 🔧 Fixed

- **Crawler Bugs**
  - Fixed `$that` closure binding (now uses proper closure binding)
  - Fixed race conditions in concurrent crawling
  - Fixed memory leaks in long-running crawls
  - Fixed duplicate URL detection
  - Fixed canonical URL handling
  - Proper content-type detection

- **Fetcher Issues**
  - Fixed logger not being used
  - Added proper URL validation
  - Improved error handling for network failures
  - Better redirect handling
  - Fixed timeout configurations

- **Parser Problems**
  - Removed `@` error suppression (now uses `libxml_use_internal_errors`)
  - Fixed UTF-8 encoding issues
  - Better handling of malformed HTML
  - Fixed relative URL resolution
  - Improved fragment handling

- **RobotsTxt Issues**
  - Fixed simple pattern matching (now supports wildcards)
  - Fixed Allow rule precedence
  - Better comment handling
  - Fixed empty rule handling
  - Improved user-agent matching

- **SitemapWriter Issues**
  - Added path traversal protection
  - Fixed directory creation permissions
  - Improved error messages
  - Better file validation
  - Fixed gzip compression errors

- **General Improvements**
  - PHP 8.0+ compatibility
  - Better memory management
  - Improved performance
  - Reduced code duplication
  - Better code organization

### 🚀 Performance

- Optimized link extraction algorithm
- Better memory usage in large crawls
- Improved concurrent request handling
- Faster XML generation
- Reduced redundant operations

### 🔒 Security

- Path traversal prevention in SitemapWriter
- XML injection prevention
- URL validation and sanitization
- Proper escaping in all outputs
- Safe file operations

### 📚 Documentation

- Comprehensive README with examples
- Detailed API documentation
- CLI usage examples
- Docker usage guide
- Best practices section
- Performance tips
- Security considerations

### 🧪 Testing

- Increased test coverage
- Added edge case tests
- Malformed input tests
- Integration tests
- Better test organization

### ⚠️ Breaking Changes

- `Crawler` constructor now accepts optional `LoggerInterface`
- `Fetcher` constructor now accepts optional `LoggerInterface`
- Some internal method signatures changed
- Improved exception handling may affect error catching

### 📝 Notes

This release focuses on production-readiness, reliability, and developer experience. All known bugs have been fixed, and the codebase now follows best practices for PHP development.

---

## [2.0.0] - Initial Release

### Added
- Basic sitemap generation
- Concurrent crawling
- Robots.txt support
- Gzip compression
- CLI tool
- Docker support
