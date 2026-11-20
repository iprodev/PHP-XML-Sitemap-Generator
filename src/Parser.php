<?php

namespace IProDev\Sitemap;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class Parser
{
    private const SKIP_SCHEMES = ['mailto:', 'javascript:', 'tel:', 'fax:', 'data:', 'file:', 'ftp:'];

    /**
     * Parse an HTML page once and extract everything the crawler needs.
     *
     * Links are resolved against <base href> when present; links with
     * rel="nofollow" are left out of "links".
     *
     * @return array{
     *     links: string[],
     *     canonical: ?string,
     *     robots: string[],
     *     title: ?string,
     *     meta_description: ?string,
     *     h1: ?string,
     *     lang: ?string,
     *     word_count: int,
     *     image_count: int,
     *     link_count: int
     * }
     */
    public static function parseDocument(string $html, string $baseUrl): array
    {
        $result = [
            'links' => [],
            'canonical' => null,
            'robots' => [],
            'title' => null,
            'meta_description' => null,
            'h1' => null,
            'lang' => null,
            'word_count' => 0,
            'image_count' => 0,
            'link_count' => 0,
        ];

        $dom = self::loadHtml($html);
        if ($dom === null) {
            return $result;
        }

        $base = self::documentBase($dom, $baseUrl);
        $result['links'] = self::collectLinks($dom, $base, true, $result['link_count']);
        $result['canonical'] = self::findCanonical($dom, $base);
        $result['robots'] = self::collectMetaRobots($dom);

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower(trim($meta->getAttribute('name'))) === 'description') {
                $result['meta_description'] = self::cleanText($meta->getAttribute('content'));
                break;
            }
        }

        $title = $dom->getElementsByTagName('title')->item(0);
        $result['title'] = $title ? self::cleanText($title->textContent) : null;

        $h1 = $dom->getElementsByTagName('h1')->item(0);
        $result['h1'] = $h1 ? self::cleanText($h1->textContent) : null;

        $html = $dom->getElementsByTagName('html')->item(0);
        if ($html instanceof \DOMElement && trim($html->getAttribute('lang')) !== '') {
            $result['lang'] = trim($html->getAttribute('lang'));
        }

        $result['image_count'] = $dom->getElementsByTagName('img')->length;
        $result['word_count'] = Utils::countWords(self::visibleText($dom));

        return $result;
    }

    /**
     * Extract absolute links from HTML.
     * @return string[]
     */
    public static function extractLinks(string $html, string $baseUrl): array
    {
        if (empty($html)) {
            return [];
        }

        $dom = self::loadHtml($html);
        if ($dom === null) {
            throw new \RuntimeException('Failed to parse HTML');
        }

        $count = 0;
        return self::collectLinks($dom, self::documentBase($dom, $baseUrl), false, $count);
    }

    /**
     * Resolve a possibly relative URL against a base URL.
     * @throws \InvalidArgumentException
     */
    public static function resolveUrl(string $href, string $base): ?string
    {
        if (empty($href) || empty($base)) {
            return null;
        }

        if (self::shouldSkipLink($href)) {
            return null;
        }

        // Validate URL doesn't contain invalid characters in scheme
        // Check if URL looks like it has a scheme (contains ://) but scheme part is malformed
        if (preg_match('/^([^:\/]+):\/\//', $href, $matches)) {
            $schemePart = $matches[1];
            // Valid scheme must match [a-z][a-z0-9+.-]*
            if (!preg_match('/^[a-z][a-z0-9+.-]*$/i', $schemePart)) {
                throw new \InvalidArgumentException("Invalid URL scheme in '{$href}'");
            }
            // Must be http or https
            if (!preg_match('/^https?$/i', $schemePart)) {
                throw new \InvalidArgumentException("Invalid URL scheme in '{$href}'");
            }
        }

        try {
            $baseUri = new Uri($base);
            $hrefUri = new Uri($href);
            $resolved = UriResolver::resolve($baseUri, $hrefUri);

            $scheme = strtolower($resolved->getScheme());
            if (!in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            // Normalize URL (remove fragment)
            return (string)$resolved->withFragment('');
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                "Failed to resolve URL '{$href}' against base '{$base}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Return canonical URL if present.
     */
    public static function getCanonical(string $html, string $baseUrl): ?string
    {
        if (empty($html)) {
            return null;
        }

        $dom = self::loadHtml($html);
        return $dom === null ? null : self::findCanonical($dom, self::documentBase($dom, $baseUrl));
    }

    /**
     * Extract meta robots directives (robots and googlebot)
     */
    public static function getMetaRobots(string $html): array
    {
        if (empty($html)) {
            return [];
        }

        $dom = self::loadHtml($html);
        return $dom === null ? [] : self::collectMetaRobots($dom);
    }

    /**
     * Convert an HTML document to UTF-8 using the charset from the Content-Type
     * header or a <meta> tag. Documents without a declared charset, or in a
     * charset that cannot be converted, are returned unchanged.
     */
    public static function toUtf8(string $html, string $contentType = ''): string
    {
        $charset = null;
        if (preg_match('/charset\s*=\s*["\']?([\w.:-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset\s*=\s*["\']?([\w.:-]+)/i', substr($html, 0, 4096), $m)) {
            $charset = $m[1];
        }

        if ($charset === null || in_array(strtolower($charset), ['utf-8', 'utf8'], true)) {
            return $html;
        }

        $converted = false;
        try {
            $converted = mb_convert_encoding($html, 'UTF-8', $charset);
        } catch (\ValueError $e) {
            // Charset not supported by mbstring, try iconv below
        }

        if (!is_string($converted) && function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $html);
        }

        return is_string($converted) && $converted !== '' ? $converted : $html;
    }

    /**
     * Load HTML (expected to be UTF-8) into a DOM document
     */
    private static function loadHtml(string $html): ?\DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $previousValue = libxml_use_internal_errors(true);

        try {
            // libxml assumes ISO-8859-1 without a declaration; numeric entities keep UTF-8 text intact
            $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
            $loaded = $dom->loadHTML($encoded, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT);
            return $loaded ? $dom : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);
        }
    }

    /**
     * Base URL for relative links: <base href> if present, else the page URL
     */
    private static function documentBase(\DOMDocument $dom, string $pageUrl): string
    {
        $base = $dom->getElementsByTagName('base')->item(0);
        if ($base instanceof \DOMElement && trim($base->getAttribute('href')) !== '') {
            try {
                $resolved = self::resolveUrl(trim($base->getAttribute('href')), $pageUrl);
                if ($resolved !== null) {
                    return $resolved;
                }
            } catch (\Throwable $e) {
                // Invalid <base>, fall back to the page URL
            }
        }

        return $pageUrl;
    }

    /**
     * @return string[]
     */
    private static function collectLinks(\DOMDocument $dom, string $base, bool $skipNofollow, int &$count): array
    {
        $links = [];

        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $count++;

            if (self::shouldSkipLink($href)) {
                continue;
            }

            if ($skipNofollow && self::hasToken($a->getAttribute('rel'), 'nofollow')) {
                continue;
            }

            try {
                $resolved = self::resolveUrl($href, $base);
                if ($resolved !== null) {
                    $links[$resolved] = true;
                }
            } catch (\Throwable $e) {
                // Skip invalid URLs
                continue;
            }
        }

        return array_keys($links);
    }

    private static function findCanonical(\DOMDocument $dom, string $base): ?string
    {
        foreach ($dom->getElementsByTagName('link') as $link) {
            if (!self::hasToken($link->getAttribute('rel'), 'canonical')) {
                continue;
            }

            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            try {
                $resolved = self::resolveUrl($href, $base);
                if ($resolved !== null) {
                    return $resolved;
                }
            } catch (\Throwable $e) {
                // Invalid canonical URL, continue searching
                continue;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function collectMetaRobots(\DOMDocument $dom): array
    {
        $directives = [];

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $name = strtolower(trim($meta->getAttribute('name')));

            if ($name === 'robots' || $name === 'googlebot') {
                $content = strtolower(trim($meta->getAttribute('content')));
                if ($content !== '') {
                    $directives = array_merge($directives, array_map('trim', explode(',', $content)));
                }
            }
        }

        return array_values(array_unique($directives));
    }

    /**
     * Text a visitor would read: the body without scripts, styles and templates
     */
    private static function visibleText(\DOMDocument $dom): string
    {
        // Join text nodes with spaces: textContent would glue "<h1>a</h1><p>b</p>" into "ab"
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query(
            '//body//text()[not(ancestor::script) and not(ancestor::style)'
            . ' and not(ancestor::noscript) and not(ancestor::template)]'
        );
        if ($nodes === false) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            $parts[] = $node->nodeValue;
        }

        return implode(' ', $parts);
    }

    private static function cleanText(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        return $text === '' ? null : $text;
    }

    /**
     * Whether a space-separated attribute value (like rel) contains a token
     */
    private static function hasToken(string $value, string $token): bool
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        return in_array($token, $tokens, true);
    }

    /**
     * Check if link should be skipped (fragments and non-http schemes)
     */
    private static function shouldSkipLink(string $href): bool
    {
        if (str_starts_with($href, '#')) {
            return true;
        }

        $lowercaseHref = strtolower($href);
        foreach (self::SKIP_SCHEMES as $scheme) {
            if (str_starts_with($lowercaseHref, $scheme)) {
                return true;
            }
        }

        return false;
    }
}
