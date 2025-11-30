<?php

namespace IProDev\Sitemap\Sitemap;

class NewsSitemapWriter
{
    /**
     * Write news sitemap (for news published in last 2 days)
     */
    public static function write(array $pages, string $outPath, ?string $publicBase = null): array
    {
        if (!is_dir($outPath)) {
            mkdir($outPath, 0755, true);
        }

        // Filter news from last 2 days
        $twoDaysAgo = strtotime('-2 days');
        $recentNews = array_filter($pages, function ($page) use ($twoDaysAgo) {
            if (empty($page['publication_date'])) {
                return false;
            }
            $pubTime = strtotime($page['publication_date']);
            return $pubTime >= $twoDaysAgo;
        });

        $files = [];
        $chunks = array_chunk($recentNews, 1000); // Google recommends max 1000 for news

        foreach ($chunks as $i => $chunk) {
            $filename = 'sitemap-news-' . ($i + 1) . '.xml';
            $filepath = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            $xml = new \XMLWriter();
            $xml->openMemory();
            $xml->setIndent(true);
            $xml->setIndentString('  ');
            $xml->startDocument('1.0', 'UTF-8');

            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $xml->writeAttribute('xmlns:news', 'http://www.google.com/schemas/sitemap-news/0.9');

            foreach ($chunk as $page) {
                $xml->startElement('url');
                $xml->writeElement('loc', htmlspecialchars($page['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));

                $xml->startElement('news:news');

                // Publication info
                $xml->startElement('news:publication');
                $pubName = $page['publication_name'] ?? 'News Site';
                $encFlags = ENT_XML1 | ENT_QUOTES;
                $xml->writeElement('news:name', htmlspecialchars($pubName, $encFlags, 'UTF-8'));
                $xml->writeElement('news:language', $page['language'] ?? 'en');
                $xml->endElement(); // news:publication

                // Publication date (W3C format)
                $pubDate = date('Y-m-d\TH:i:s\Z', strtotime($page['publication_date']));
                $xml->writeElement('news:publication_date', $pubDate);

                // Title
                $xml->writeElement('news:title', htmlspecialchars($page['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));

                // Optional: keywords
                if (!empty($page['keywords'])) {
                    $keywords = is_array($page['keywords']) ? implode(', ', $page['keywords']) : $page['keywords'];
                    $xml->writeElement('news:keywords', htmlspecialchars($keywords, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                }

                $xml->endElement(); // news:news
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
     * Extract news metadata from HTML
     */
    public static function extractNewsMetadata(string $html): array
    {
        $metadata = [];
        $dom = new \DOMDocument();

        $previousValue = libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previousValue);

        $metaTags = $dom->getElementsByTagName('meta');

        foreach ($metaTags as $meta) {
            $property = strtolower($meta->getAttribute('property'));
            $name = strtolower($meta->getAttribute('name'));
            $content = $meta->getAttribute('content');

            // OpenGraph
            if ($property === 'article:published_time') {
                $metadata['publication_date'] = $content;
            } elseif ($property === 'og:title') {
                $metadata['title'] = $content;
            }

            // Standard meta tags
            if ($name === 'keywords') {
                $metadata['keywords'] = $content;
            } elseif ($name === 'news_keywords') {
                $metadata['keywords'] = $content;
            } elseif ($name === 'description') {
                $metadata['description'] = $content;
            }
        }

        // Try to find publication date in JSON-LD
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $matches)) {
            $jsonLd = json_decode($matches[1], true);
            if (isset($jsonLd['datePublished'])) {
                $metadata['publication_date'] = $jsonLd['datePublished'];
            }
        }

        return $metadata;
    }
}
