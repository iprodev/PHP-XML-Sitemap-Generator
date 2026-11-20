<?php

namespace IProDev\Sitemap;

class SitemapWriter
{
    private const MAX_URLS_PER_FILE = 50000;
    private const MAX_FILE_SIZE_MB = 50;

    /**
     * Write sitemap files
     * @param array<int,array{url:string,status:int,lastmod:?string}> $pages
     * @param string $outPath directory path to write files
     * @param int $maxPerFile maximum URLs per sitemap file (<= 50,000 per spec)
     * @param string|null $publicBase public base URL used to build <loc> for sitemap files in the index;
     *                                defaults to the origin (scheme://host) of the first page
     * @param string[] $additionalSitemaps other sitemap files in $outPath (e.g. image sitemaps) to list in the index
     * @return string[] list of generated file paths
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function write(
        array $pages,
        string $outPath,
        int $maxPerFile = 50000,
        ?string $publicBase = null,
        array $additionalSitemaps = []
    ): array {
        // Validate inputs
        if (empty($pages)) {
            throw new \InvalidArgumentException('No pages to write to sitemap');
        }

        if (empty($outPath)) {
            throw new \InvalidArgumentException('Output path cannot be empty');
        }

        // Sanitize path
        $outPath = self::sanitizePath($outPath);

        // Create directory if it doesn't exist
        if (!is_dir($outPath)) {
            if (!mkdir($outPath, 0755, true)) {
                throw new \RuntimeException("Failed to create output directory: {$outPath}");
            }
        }

        // Verify directory is writable
        if (!is_writable($outPath)) {
            throw new \RuntimeException("Output directory is not writable: {$outPath}");
        }

        // Validate maxPerFile
        $maxPerFile = max(1, min($maxPerFile, self::MAX_URLS_PER_FILE));

        // Filter valid pages
        $validPages = self::filterValidPages($pages);

        if (empty($validPages)) {
            throw new \InvalidArgumentException('No valid pages found to write');
        }

        $files = [];
        $chunks = array_chunk($validPages, $maxPerFile);

        foreach ($chunks as $i => $chunk) {
            try {
                $fileNumber = $i + 1;
                $xmlFile = self::writeSitemapFile($chunk, $outPath, $fileNumber);
                $gzFile = self::compressSitemap($xmlFile);
                $files[] = $gzFile;
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Failed to write sitemap chunk {$i}: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        // The sitemap protocol requires absolute URLs in the index. When no public
        // base is given, assume the files are served from the root of the crawled site.
        if ($publicBase === null || $publicBase === '') {
            $publicBase = self::originOf($validPages[0]['url']);
        }

        // Create sitemap index
        try {
            $indexPath = self::writeSitemapIndex(array_merge($files, $additionalSitemaps), $outPath, $publicBase);
            $files[] = $indexPath;
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to write sitemap index: " . $e->getMessage(), 0, $e);
        }

        return $files;
    }

    /**
     * Filter and validate pages
     */
    private static function filterValidPages(array $pages): array
    {
        $valid = [];

        foreach ($pages as $page) {
            if (!isset($page['url']) || !is_string($page['url']) || empty($page['url'])) {
                continue;
            }

            // Validate URL format
            if (!filter_var($page['url'], FILTER_VALIDATE_URL)) {
                continue;
            }

            // Ensure required fields
            if (!isset($page['status'])) {
                $page['status'] = 200;
            }

            if (!isset($page['lastmod'])) {
                $page['lastmod'] = null;
            }

            $valid[] = $page;
        }

        return $valid;
    }

    /**
     * Write a single sitemap XML file
     */
    private static function writeSitemapFile(array $pages, string $outPath, int $fileNumber): string
    {
        $filename = 'sitemap-' . $fileNumber . '.xml';
        $filepath = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->writeAttribute(
            'xsi:schemaLocation',
            'http://www.sitemaps.org/schemas/sitemap/0.9 ' .
            'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd'
        );

        foreach ($pages as $page) {
            $xml->startElement('url');

            // Required: location (XMLWriter escapes special characters itself)
            $xml->writeElement('loc', $page['url']);

            // Optional: last modified
            if (!empty($page['lastmod']) && self::isValidDate($page['lastmod'])) {
                $xml->writeElement('lastmod', $page['lastmod']);
            }

            // Optional: priority (0.0 - 1.0), e.g. from UrlFilter priority rules
            if (isset($page['priority']) && is_numeric($page['priority'])) {
                $priority = max(0.0, min(1.0, (float)$page['priority']));
                $xml->writeElement('priority', number_format($priority, 1, '.', ''));
            }

            $xml->endElement(); // url
        }

        $xml->endElement(); // urlset
        $xml->endDocument();

        $content = $xml->outputMemory();

        if (file_put_contents($filepath, $content) === false) {
            throw new \RuntimeException("Failed to write file: {$filepath}");
        }

        return $filepath;
    }

    /**
     * Compress sitemap file with gzip
     */
    private static function compressSitemap(string $xmlPath): string
    {
        if (!file_exists($xmlPath)) {
            throw new \RuntimeException("XML file not found: {$xmlPath}");
        }

        $content = file_get_contents($xmlPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read XML file: {$xmlPath}");
        }

        $gzPath = $xmlPath . '.gz';

        $fp = gzopen($gzPath, 'w9');
        if ($fp === false) {
            throw new \RuntimeException("Failed to create gzip file: {$gzPath}");
        }

        $written = gzwrite($fp, $content);
        gzclose($fp);

        if ($written === false || $written === 0) {
            throw new \RuntimeException("Failed to write gzip content: {$gzPath}");
        }

        return $gzPath;
    }

    /**
     * Write sitemap index file
     */
    private static function writeSitemapIndex(array $gzFiles, string $outPath, string $publicBase): string
    {
        $indexPath = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sitemap-index.xml';

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($gzFiles as $gzFilePath) {
            // Skip the index file itself if it somehow got into the list
            if (basename($gzFilePath) === 'sitemap-index.xml') {
                continue;
            }

            $gzFilename = basename($gzFilePath);
            $loc = rtrim($publicBase, '/') . '/' . $gzFilename;

            $xml->startElement('sitemap');
            $xml->writeElement('loc', $loc);

            // W3C datetime with the real UTC offset of the configured timezone
            $mtime = filemtime($gzFilePath);
            $lastmod = date(DATE_ATOM, $mtime !== false ? $mtime : time());

            $xml->writeElement('lastmod', $lastmod);
            $xml->endElement(); // sitemap
        }

        $xml->endElement(); // sitemapindex
        $xml->endDocument();

        $content = $xml->outputMemory();

        if (file_put_contents($indexPath, $content) === false) {
            throw new \RuntimeException("Failed to write sitemap index: {$indexPath}");
        }

        return $indexPath;
    }

    /**
     * Sanitize file path to prevent directory traversal
     */
    private static function sanitizePath(string $path): string
    {
        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize path separators
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        // Remove relative path components
        $path = realpath($path) ?: $path;

        return $path;
    }

    /**
     * Return scheme://host[:port] of a URL
     */
    private static function originOf(string $url): string
    {
        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    /**
     * Validate date format (Y-m-d or ISO 8601)
     */
    private static function isValidDate(string $date): bool
    {
        $formats = ['Y-m-d', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s\Z'];

        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }

        return false;
    }
}
