<?php

namespace IProDev\Sitemap\Sitemap;

use IProDev\Sitemap\Parser;

class NewsSitemapWriter
{
    private const NAMESPACE_URI = 'http://www.google.com/schemas/sitemap-news/0.9';

    /** Google News sitemaps may hold at most 1,000 URLs */
    private const MAX_URLS_PER_FILE = 1000;

    /**
     * Write news sitemap files for articles published in the last 2 days.
     *
     * @param array $pages records with url, publication_date, title and optionally
     *                     publication_name, language and keywords
     * @return string[] paths of the written .gz files (none when there are no recent articles)
     */
    public static function write(array $pages, string $outPath, ?string $publicBase = null): array
    {
        $twoDaysAgo = strtotime('-2 days');
        $recentNews = array_values(array_filter($pages, function ($page) use ($twoDaysAgo) {
            if (empty($page['url']) || empty($page['title']) || empty($page['publication_date'])) {
                return false;
            }
            $pubTime = strtotime($page['publication_date']);
            return $pubTime !== false && $pubTime >= $twoDaysAgo;
        }));

        $files = [];
        foreach (array_chunk($recentNews, self::MAX_URLS_PER_FILE) as $i => $chunk) {
            $xml = SitemapFile::startUrlset(['news' => self::NAMESPACE_URI]);

            foreach ($chunk as $page) {
                $xml->startElement('url');
                $xml->writeElement('loc', $page['url']);

                $xml->startElement('news:news');

                $xml->startElement('news:publication');
                $xml->writeElement('news:name', $page['publication_name'] ?? 'News');
                $xml->writeElement('news:language', self::languageCode($page['language'] ?? null));
                $xml->endElement(); // news:publication

                $xml->writeElement(
                    'news:publication_date',
                    gmdate('Y-m-d\TH:i:s\Z', (int)strtotime($page['publication_date']))
                );
                $xml->writeElement('news:title', $page['title']);

                if (!empty($page['keywords'])) {
                    $keywords = is_array($page['keywords']) ? implode(', ', $page['keywords']) : $page['keywords'];
                    $xml->writeElement('news:keywords', $keywords);
                }

                $xml->endElement(); // news:news
                $xml->endElement(); // url
            }

            $files[] = SitemapFile::write($outPath, 'sitemap-news-' . ($i + 1) . '.xml', SitemapFile::finish($xml));
        }

        return $files;
    }

    /**
     * ISO 639 language code as Google News expects it ("fa", "en", "zh-cn", "zh-tw")
     */
    public static function languageCode(?string $lang): string
    {
        $lang = strtolower(str_replace('_', '-', trim((string)$lang)));
        if ($lang === '') {
            return 'en';
        }
        if (in_array($lang, ['zh-cn', 'zh-tw'], true)) {
            return $lang;
        }

        return explode('-', $lang)[0];
    }

    /**
     * Extract news metadata from HTML: publication date and headline from OpenGraph
     * article tags or JSON-LD, plus keywords, description and site name.
     *
     * @return array<string, string>
     */
    public static function extractNewsMetadata(string $html): array
    {
        $dom = Parser::loadDocument($html);
        if ($dom === null) {
            return [];
        }

        $metadata = [];

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $property = strtolower($meta->getAttribute('property'));
            $name = strtolower($meta->getAttribute('name'));
            $content = trim($meta->getAttribute('content'));
            if ($content === '') {
                continue;
            }

            if ($property === 'article:published_time') {
                $metadata['publication_date'] = $content;
            } elseif ($property === 'og:title') {
                $metadata['title'] = $content;
            } elseif ($property === 'og:site_name') {
                $metadata['publication_name'] = $content;
            }

            if ($name === 'news_keywords' || ($name === 'keywords' && !isset($metadata['keywords']))) {
                $metadata['keywords'] = $content;
            } elseif ($name === 'description') {
                $metadata['description'] = $content;
            }
        }

        foreach ($dom->getElementsByTagName('script') as $script) {
            if (strtolower(trim($script->getAttribute('type'))) !== 'application/ld+json') {
                continue;
            }

            $data = json_decode($script->textContent, true);
            if (!is_array($data)) {
                continue;
            }

            $published = self::findJsonLdValue($data, 'datePublished');
            if ($published !== null && !isset($metadata['publication_date'])) {
                $metadata['publication_date'] = $published;
            }
            $headline = self::findJsonLdValue($data, 'headline');
            if ($headline !== null && !isset($metadata['title'])) {
                $metadata['title'] = $headline;
            }
        }

        return $metadata;
    }

    /**
     * First string value of $key anywhere in a JSON-LD structure (handles @graph and lists)
     */
    private static function findJsonLdValue(array $data, string $key): ?string
    {
        if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
            return $data[$key];
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::findJsonLdValue($value, $key);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
