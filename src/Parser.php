<?php
namespace IProDev\Sitemap;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class Parser {
    /** Extract absolute links from HTML. */
    public static function extractLinks(string $html, string $baseUrl): array {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if (!$href) continue;
            $resolved = self::resolveUrl($href, $baseUrl);
            if ($resolved !== null) $links[] = $resolved;
        }
        // Deduplicate
        return array_values(array_unique($links));
    }

    /** Resolve a possibly relative URL against a base URL. */
    public static function resolveUrl(string $href, string $base): ?string {
        // Fragments and non-http schemes are ignored
        if (str_starts_with($href, '#')) return null;
        if (preg_match('#^(mailto|javascript|tel):#i', $href)) return null;

        $baseUri = new Uri($base);
        $hrefUri = new Uri($href);
        $resolved = UriResolver::resolve($baseUri, $hrefUri);
        $scheme = $resolved->getScheme();
        if (!in_array($scheme, ['http', 'https'])) return null;
        return (string)$resolved;
    }

    /** Return canonical URL if present. */
    public static function getCanonical(string $html, string $baseUrl): ?string {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        foreach ($dom->getElementsByTagName('link') as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            if ($rel === 'canonical') {
                $href = $link->getAttribute('href');
                return self::resolveUrl($href, $baseUrl);
            }
        }
        return null;
    }
}
