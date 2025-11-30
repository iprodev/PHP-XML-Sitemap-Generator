<?php

namespace IProDev\Sitemap\Sitemap;

class ImageSitemapWriter
{
    /**
     * Write image sitemap
     * @param array $pages Array with url and images data
     */
    public static function write(array $pages, string $outPath, ?string $publicBase = null): array
    {
        if (!is_dir($outPath)) {
            mkdir($outPath, 0755, true);
        }

        $files = [];
        $chunks = array_chunk($pages, 50000);

        foreach ($chunks as $i => $chunk) {
            $filename = 'sitemap-images-' . ($i + 1) . '.xml';
            $filepath = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            $xml = new \XMLWriter();
            $xml->openMemory();
            $xml->setIndent(true);
            $xml->setIndentString('  ');
            $xml->startDocument('1.0', 'UTF-8');
            
            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

            foreach ($chunk as $page) {
                if (empty($page['images'])) {
                    continue;
                }

                $xml->startElement('url');
                $xml->writeElement('loc', htmlspecialchars($page['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                
                foreach ($page['images'] as $image) {
                    $xml->startElement('image:image');
                    $xml->writeElement('image:loc', htmlspecialchars($image['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                    
                    if (!empty($image['title'])) {
                        $xml->writeElement('image:title', htmlspecialchars($image['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                    }
                    
                    if (!empty($image['caption'])) {
                        $xml->writeElement('image:caption', htmlspecialchars($image['caption'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                    }
                    
                    $xml->endElement(); // image:image
                }
                
                $xml->endElement(); // url
            }

            $xml->endElement(); // urlset
            $xml->endDocument();
            
            $content = $xml->outputMemory();
            file_put_contents($filepath, $content);

            // Gzip
            $gzPath = $filepath . '.gz';
            $fp = gzopen($gzPath, 'w9');
            gzwrite($fp, $content);
            gzclose($fp);

            $files[] = $gzPath;
        }

        return $files;
    }

    /**
     * Extract images from HTML
     */
    public static function extractImages(string $html, string $baseUrl): array
    {
        $images = [];
        $dom = new \DOMDocument();
        
        $previousValue = libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previousValue);

        $imgTags = $dom->getElementsByTagName('img');
        
        foreach ($imgTags as $img) {
            $src = $img->getAttribute('src');
            if (empty($src)) {
                continue;
            }

            $absoluteUrl = self::resolveUrl($src, $baseUrl);
            if (!$absoluteUrl) {
                continue;
            }

            $images[] = [
                'url' => $absoluteUrl,
                'title' => $img->getAttribute('title') ?: $img->getAttribute('alt'),
                'caption' => $img->getAttribute('alt')
            ];
        }

        return $images;
    }

    private static function resolveUrl(string $href, string $base): ?string
    {
        if (empty($href)) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $baseParts = parse_url($base);
        if (!$baseParts) {
            return null;
        }

        $scheme = $baseParts['scheme'] ?? 'http';
        $host = $baseParts['host'] ?? '';

        if (strpos($href, '/') === 0) {
            return $scheme . '://' . $host . $href;
        }

        $path = $baseParts['path'] ?? '/';
        $path = dirname($path);
        
        return $scheme . '://' . $host . rtrim($path, '/') . '/' . $href;
    }
}
