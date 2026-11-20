<?php

namespace IProDev\Sitemap\Sitemap;

use IProDev\Sitemap\Parser;

class VideoSitemapWriter
{
    private const NAMESPACE_URI = 'http://www.google.com/schemas/sitemap-video/1.1';
    private const MAX_DESCRIPTION_LENGTH = 2048;

    /**
     * Write video sitemap files.
     *
     * Videos without the fields Google requires (thumbnail, title, description and a
     * content or player URL) are left out; pages without such videos are skipped.
     *
     * @return string[] paths of the written .gz files
     */
    public static function write(array $pages, string $outPath, ?string $publicBase = null): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $videos = array_values(array_filter($page['videos'] ?? [], [self::class, 'isValid']));
            if (!empty($page['url']) && !empty($videos)) {
                $entries[] = ['url' => $page['url'], 'videos' => $videos];
            }
        }

        $files = [];
        foreach (array_chunk($entries, 50000) as $i => $chunk) {
            $xml = SitemapFile::startUrlset(['video' => self::NAMESPACE_URI]);

            foreach ($chunk as $page) {
                $xml->startElement('url');
                $xml->writeElement('loc', $page['url']);

                foreach ($page['videos'] as $video) {
                    $xml->startElement('video:video');
                    $xml->writeElement('video:thumbnail_loc', $video['thumbnail']);
                    $xml->writeElement('video:title', $video['title']);
                    $xml->writeElement(
                        'video:description',
                        mb_substr($video['description'], 0, self::MAX_DESCRIPTION_LENGTH)
                    );

                    if (!empty($video['content_url'])) {
                        $xml->writeElement('video:content_loc', $video['content_url']);
                    }

                    if (!empty($video['player_url'])) {
                        $xml->writeElement('video:player_loc', $video['player_url']);
                    }

                    if (!empty($video['duration'])) {
                        $xml->writeElement('video:duration', (string)(int)$video['duration']);
                    }

                    if (!empty($video['publication_date'])) {
                        $xml->writeElement('video:publication_date', $video['publication_date']);
                    }

                    if (isset($video['family_friendly'])) {
                        $xml->writeElement('video:family_friendly', $video['family_friendly'] ? 'yes' : 'no');
                    }

                    foreach (array_slice($video['tags'] ?? [], 0, 32) as $tag) {
                        $xml->writeElement('video:tag', (string)$tag);
                    }

                    $xml->endElement(); // video:video
                }

                $xml->endElement(); // url
            }

            $files[] = SitemapFile::write($outPath, 'sitemap-videos-' . ($i + 1) . '.xml', SitemapFile::finish($xml));
        }

        return $files;
    }

    /**
     * Whether a video has the fields required by Google's video sitemap format
     */
    public static function isValid(array $video): bool
    {
        return !empty($video['thumbnail']) && !empty($video['title']) && !empty($video['description'])
            && (!empty($video['content_url']) || !empty($video['player_url']));
    }

    /**
     * Extract embedded YouTube and Vimeo players and HTML5 <video> elements.
     *
     * The page title and description are used as video title and description;
     * the thumbnail is the video poster, the YouTube thumbnail or the page's
     * og:image. Videos without a thumbnail are left out.
     *
     * @return array<int, array>
     */
    public static function extractVideos(string $html, string $baseUrl): array
    {
        $dom = Parser::loadDocument($html);
        if ($dom === null) {
            return [];
        }

        $base = Parser::documentBase($dom, $baseUrl);
        $meta = self::pageMeta($dom, $base);
        $videos = [];

        $add = function (array $video) use (&$videos, $meta) {
            $video += ['title' => $meta['title'], 'description' => $meta['description'], 'family_friendly' => true];
            if (empty($video['thumbnail'])) {
                $video['thumbnail'] = $meta['image'];
            }
            $key = $video['player_url'] ?? $video['content_url'] ?? '';
            if ($key !== '' && !isset($videos[$key]) && self::isValid($video)) {
                $videos[$key] = $video;
            }
        };

        foreach ($dom->getElementsByTagName('iframe') as $iframe) {
            $src = trim($iframe->getAttribute('src') ?: $iframe->getAttribute('data-src'));
            $youtube = '#^(?:https?:)?//(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,})#';
            if (preg_match($youtube, $src, $m)) {
                $add([
                    'player_url' => "https://www.youtube.com/embed/{$m[1]}",
                    'thumbnail' => "https://i.ytimg.com/vi/{$m[1]}/hqdefault.jpg",
                ]);
            } elseif (preg_match('#^(?:https?:)?//player\.vimeo\.com/video/([0-9]+)#', $src, $m)) {
                $add(['player_url' => "https://player.vimeo.com/video/{$m[1]}"]);
            }
        }

        foreach ($dom->getElementsByTagName('video') as $video) {
            $src = trim($video->getAttribute('src'));
            if ($src === '') {
                $source = $video->getElementsByTagName('source')->item(0);
                $src = $source instanceof \DOMElement ? trim($source->getAttribute('src')) : '';
            }
            $contentUrl = self::resolve($src, $base);
            if ($contentUrl === null) {
                continue;
            }

            $add([
                'content_url' => $contentUrl,
                'thumbnail' => self::resolve(trim($video->getAttribute('poster')), $base),
            ]);
        }

        return array_values($videos);
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string}
     */
    private static function pageMeta(\DOMDocument $dom, string $base): array
    {
        $values = [];
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $key = strtolower(trim($meta->getAttribute('property') ?: $meta->getAttribute('name')));
            if ($key !== '' && !isset($values[$key])) {
                $values[$key] = trim($meta->getAttribute('content'));
            }
        }

        $titleNode = $dom->getElementsByTagName('title')->item(0);
        $title = ($values['og:title'] ?? '') ?: ($titleNode ? trim($titleNode->textContent) : '');
        $description = ($values['og:description'] ?? '') ?: ($values['description'] ?? '') ?: $title;

        return [
            'title' => $title !== '' ? $title : null,
            'description' => $description !== '' ? $description : null,
            'image' => self::resolve($values['og:image'] ?? '', $base),
        ];
    }

    private static function resolve(string $url, string $base): ?string
    {
        if ($url === '') {
            return null;
        }

        try {
            return Parser::resolveUrl($url, $base);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
