# PHP XML Sitemap Generator (Library + CLI)

A professional PHP sitemap generator by **iprodev** — supports concurrency, robots.txt, gzip, and sitemap index files.

## Install

```bash
composer require iprodev/sitemap-generator-pro
```

## CLI Usage

```bash
php bin/sitemap --url=https://www.iprodev.com --out=./sitemaps --concurrency=20 --max-pages=10000 --max-depth=5 --public-base=https://www.iprodev.com
```

**Options**

- `--url` *(required)*: Start URL (must be same-host for crawling)
- `--out` *(default: ./output)*: Output directory
- `--concurrency` *(default: 10)*: Number of concurrent HTTP requests
- `--max-pages` *(default: 50000)*: Crawl limit
- `--max-depth` *(default: 5)*: Max link depth
- `--public-base` *(optional)*: Public base URL for sitemap file URLs in `sitemap-index.xml`

## Programmatic Usage

```php
use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

$fetcher = new Fetcher(['concurrency' => 10]);
$robots  = RobotsTxt::fromUrl('https://www.iprodev.com', $fetcher);
$crawler = new Crawler($fetcher, $robots);

$pages = $crawler->crawl('https://www.iprodev.com', 10000, 5);
SitemapWriter::write($pages, __DIR__ . '/sitemaps', 50000, 'https://www.iprodev.com');
```

## Tests & Lint

```bash
composer install
vendor/bin/phpunit

vendor/bin/phpcs --standard=PSR12 src/ tests/
```

## Docker

```bash
docker build -t sitemap-generator-pro .
docker run --rm -v $(pwd)/sitemaps:/app/output sitemap-generator-pro --url=https://www.iprodev.com --out=/app/output --concurrency=20 --max-pages=10000 --public-base=https://www.iprodev.com
```

## License

MIT
