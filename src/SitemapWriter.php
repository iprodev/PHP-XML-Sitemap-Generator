<?php
namespace IProDev\Sitemap;

class SitemapWriter {
    /**
     * @param array<int,array{url:string,status:int,lastmod:?string}> $pages
     * @param string $outPath directory path to write files
     * @param int $maxPerFile maximum URLs per sitemap file (<= 50,000 per spec)
     * @param string|null $publicBase public base URL used to build <loc> for sitemap files in the index (e.g., https://www.example.com)
     * @return string[] list of generated file paths
     */
    public static function write(array $pages, string $outPath, int $maxPerFile = 50000, ?string $publicBase = null): array {
        if (!is_dir($outPath)) {
            mkdir($outPath, 0755, true);
        }

        // sanitize
        $maxPerFile = max(1, min($maxPerFile, 50000));

        $files = [];
        $chunks = array_chunk($pages, $maxPerFile);
        foreach ($chunks as $i => $chunk) {
            $base = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sitemap-' . ($i + 1) . '.xml';
            $xml = new \XMLWriter();
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            foreach ($chunk as $p) {
                $xml->startElement('url');
                $xml->writeElement('loc', $p['url']);
                if (!empty($p['lastmod'])) {
                    $xml->writeElement('lastmod', $p['lastmod']);
                }
                $xml->endElement();
            }

            $xml->endElement(); // urlset
            $xml->endDocument();
            $content = $xml->outputMemory();

            file_put_contents($base, $content);

            // gzip version
            $gz = $base . '.gz';
            $fp = gzopen($gz, 'w9');
            gzwrite($fp, $content);
            gzclose($fp);

            $files[] = $gz;
        }

        // create index
        $indexPath = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sitemap-index.xml';
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($files as $gzFilePath) {
            $gzFilename = basename($gzFilePath);
            $loc = $gzFilename;
            if ($publicBase) {
                $loc = rtrim($publicBase, '/') . '/' . $gzFilename;
            }
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $loc);
            $xml->writeElement('lastmod', date('Y-m-d'));
            $xml->endElement();
        }
        $xml->endElement(); // sitemapindex
        $xml->endDocument();
        file_put_contents($indexPath, $xml->outputMemory());

        $files[] = $indexPath;
        return $files;
    }
}
