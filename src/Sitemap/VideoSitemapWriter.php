<?php

namespace IProDev\Sitemap\Sitemap;

class VideoSitemapWriter
{
    /**
     * Write video sitemap
     */
    public static function write(array $pages, string $outPath, ?string $publicBase = null): array
    {
        if (!is_dir($outPath)) {
            mkdir($outPath, 0755, true);
        }

        $files = [];
        $chunks = array_chunk($pages, 50000);

        foreach ($chunks as $i => $chunk) {
            $filename = 'sitemap-videos-' . ($i + 1) . '.xml';
            $filepath = rtrim($outPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            $xml = new \XMLWriter();
            $xml->openMemory();
            $xml->setIndent(true);
            $xml->setIndentString('  ');
            $xml->startDocument('1.0', 'UTF-8');

            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $xml->writeAttribute('xmlns:video', 'http://www.google.com/schemas/sitemap-video/1.1');

            foreach ($chunk as $page) {
                if (empty($page['videos'])) {
                    continue;
                }

                $xml->startElement('url');
                $xml->writeElement('loc', htmlspecialchars($page['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));

                $encFlags = ENT_XML1 | ENT_QUOTES;
                foreach ($page['videos'] as $video) {
                    $xml->startElement('video:video');

                    // Required fields
                    $thumb = htmlspecialchars($video['thumbnail'], $encFlags, 'UTF-8');
                    $xml->writeElement('video:thumbnail_loc', $thumb);
                    $title = htmlspecialchars($video['title'], $encFlags, 'UTF-8');
                    $xml->writeElement('video:title', $title);
                    $desc = htmlspecialchars($video['description'], $encFlags, 'UTF-8');
                    $xml->writeElement('video:description', $desc);

                    // Optional: content location
                    if (!empty($video['content_url'])) {
                        $contentUrl = htmlspecialchars($video['content_url'], $encFlags, 'UTF-8');
                        $xml->writeElement('video:content_loc', $contentUrl);
                    }

                    // Optional: player location
                    if (!empty($video['player_url'])) {
                        $playerUrl = htmlspecialchars($video['player_url'], $encFlags, 'UTF-8');
                        $xml->writeElement('video:player_loc', $playerUrl);
                    }

                    // Optional: duration (seconds)
                    if (!empty($video['duration'])) {
                        $xml->writeElement('video:duration', (string)$video['duration']);
                    }

                    // Optional: publication date
                    if (!empty($video['publication_date'])) {
                        $xml->writeElement('video:publication_date', $video['publication_date']);
                    }

                    // Optional: family friendly
                    if (isset($video['family_friendly'])) {
                        $xml->writeElement('video:family_friendly', $video['family_friendly'] ? 'yes' : 'no');
                    }

                    // Optional: tags
                    if (!empty($video['tags'])) {
                        foreach ($video['tags'] as $tag) {
                            $xml->writeElement('video:tag', htmlspecialchars($tag, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                        }
                    }

                    $xml->endElement(); // video:video
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
     * Extract video data from HTML
     */
    public static function extractVideos(string $html, string $baseUrl): array
    {
        $videos = [];

        // YouTube embeds
        if (preg_match_all('#youtube\.com/embed/([a-zA-Z0-9_-]+)#', $html, $matches)) {
            foreach ($matches[1] as $videoId) {
                $videos[] = [
                    'content_url' => "https://www.youtube.com/watch?v={$videoId}",
                    'player_url' => "https://www.youtube.com/embed/{$videoId}",
                    'thumbnail' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                    'title' => "Video {$videoId}",
                    'description' => "YouTube video",
                    'family_friendly' => true
                ];
            }
        }

        // Vimeo embeds
        if (preg_match_all('#vimeo\.com/video/([0-9]+)#', $html, $matches)) {
            foreach ($matches[1] as $videoId) {
                $videos[] = [
                    'player_url' => "https://player.vimeo.com/video/{$videoId}",
                    'content_url' => "https://vimeo.com/{$videoId}",
                    'thumbnail' => "https://vimeo.com/api/v2/video/{$videoId}.json",
                    'title' => "Vimeo Video {$videoId}",
                    'description' => "Vimeo video",
                    'family_friendly' => true
                ];
            }
        }

        // HTML5 video tags
        $dom = new \DOMDocument();
        $previousValue = libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previousValue);

        $videoTags = $dom->getElementsByTagName('video');
        foreach ($videoTags as $video) {
            $poster = $video->getAttribute('poster');
            $sources = $video->getElementsByTagName('source');

            if ($sources->length > 0) {
                $src = $sources->item(0)->getAttribute('src');
                if ($src) {
                    $videos[] = [
                        'content_url' => self::resolveUrl($src, $baseUrl),
                        'thumbnail' => $poster ? self::resolveUrl($poster, $baseUrl) : '',
                        'title' => 'HTML5 Video',
                        'description' => 'HTML5 video content',
                        'family_friendly' => true
                    ];
                }
            }
        }

        return $videos;
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
