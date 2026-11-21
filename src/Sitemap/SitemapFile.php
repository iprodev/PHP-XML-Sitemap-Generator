<?php

namespace IProDev\Sitemap\Sitemap;

/**
 * Writes a sitemap XML file and its gzip-compressed copy
 */
final class SitemapFile
{
    /**
     * @return string path of the .gz file
     */
    public static function write(string $directory, string $filename, string $content): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Failed to create output directory: {$directory}");
        }

        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }

        $gzPath = $path . '.gz';
        $compressed = gzencode($content, 9);
        if ($compressed === false || file_put_contents($gzPath, $compressed) === false) {
            throw new \RuntimeException("Failed to write gzip file: {$gzPath}");
        }

        return $gzPath;
    }

    /**
     * Start an XMLWriter document with a urlset element and the given extra namespaces
     *
     * @param array<string, string> $namespaces prefix => namespace URI
     */
    public static function startUrlset(array $namespaces): \XMLWriter
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($namespaces as $prefix => $uri) {
            $xml->writeAttribute('xmlns:' . $prefix, $uri);
        }

        return $xml;
    }

    public static function finish(\XMLWriter $xml): string
    {
        $xml->endElement(); // urlset
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
