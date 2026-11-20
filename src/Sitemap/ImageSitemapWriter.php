<?php

namespace IProDev\Sitemap\Sitemap;

use IProDev\Sitemap\Parser;

class ImageSitemapWriter
{
    /** Google accepts at most 1,000 images per page */
    public const MAX_IMAGES_PER_PAGE = 1000;

    private const NAMESPACE_URI = 'http://www.google.com/schemas/sitemap-image/1.1';

    /**
     * Write image sitemap files for the pages that have images.
     *
     * @param array $pages records with "url" and "images" (list of ["url", "title"?, "caption"?])
     * @return string[] paths of the written .gz files (none when no page has images)
     */
    public static function write(array $pages, string $outPath, ?string $publicBase = null): array
    {
        $pages = array_values(array_filter($pages, fn($page) => !empty($page['url']) && !empty($page['images'])));

        $files = [];
        foreach (array_chunk($pages, 50000) as $i => $chunk) {
            $xml = SitemapFile::startUrlset(['image' => self::NAMESPACE_URI]);

            foreach ($chunk as $page) {
                $xml->startElement('url');
                $xml->writeElement('loc', $page['url']);

                foreach (array_slice($page['images'], 0, self::MAX_IMAGES_PER_PAGE) as $image) {
                    if (empty($image['url'])) {
                        continue;
                    }

                    $xml->startElement('image:image');
                    $xml->writeElement('image:loc', $image['url']);

                    if (!empty($image['title'])) {
                        $xml->writeElement('image:title', $image['title']);
                    }

                    if (!empty($image['caption'])) {
                        $xml->writeElement('image:caption', $image['caption']);
                    }

                    $xml->endElement(); // image:image
                }

                $xml->endElement(); // url
            }

            $files[] = SitemapFile::write($outPath, 'sitemap-images-' . ($i + 1) . '.xml', SitemapFile::finish($xml));
        }

        return $files;
    }

    /**
     * Extract images from HTML (src, or data-src / data-lazy-src for lazy-loaded images)
     *
     * @return array<int, array{url: string, title: ?string, caption: ?string}>
     */
    public static function extractImages(string $html, string $baseUrl): array
    {
        $dom = Parser::loadDocument($html);
        if ($dom === null) {
            return [];
        }

        $base = Parser::documentBase($dom, $baseUrl);
        $images = [];

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = '';
            foreach (['src', 'data-src', 'data-lazy-src'] as $attribute) {
                $value = trim($img->getAttribute($attribute));
                if ($value !== '' && !str_starts_with(strtolower($value), 'data:')) {
                    $src = $value;
                    break;
                }
            }
            if ($src === '') {
                continue;
            }

            try {
                $url = Parser::resolveUrl($src, $base);
            } catch (\Throwable $e) {
                continue;
            }
            if ($url === null || isset($images[$url])) {
                continue;
            }

            $alt = trim($img->getAttribute('alt'));
            $title = trim($img->getAttribute('title'));
            $images[$url] = [
                'url' => $url,
                'title' => $title !== '' ? $title : ($alt !== '' ? $alt : null),
                'caption' => $alt !== '' ? $alt : null,
            ];

            if (count($images) >= self::MAX_IMAGES_PER_PAGE) {
                break;
            }
        }

        return array_values($images);
    }
}
