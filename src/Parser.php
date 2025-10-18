<?php
namespace IProDev\Sitemap;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class Parser {
    /** 
     * Extract absolute links from HTML.
     * @return string[]
     */
    public static function extractLinks(string $html, string $baseUrl): array {
        if (empty($html)) {
            return [];
        }

        $dom = new \DOMDocument();
        
        // Suppress warnings for malformed HTML
        $previousValue = libxml_use_internal_errors(true);
        
        try {
            // Use UTF-8 meta tag to handle encoding properly
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
            
            $links = [];
            $anchors = $dom->getElementsByTagName('a');
            
            foreach ($anchors as $a) {
                $href = $a->getAttribute('href');
                if (empty($href)) {
                    continue;
                }

                // Skip unwanted link types
                if (self::shouldSkipLink($href)) {
                    continue;
                }

                try {
                    $resolved = self::resolveUrl($href, $baseUrl);
                    if ($resolved !== null) {
                        $links[] = $resolved;
                    }
                } catch (\Throwable $e) {
                    // Skip invalid URLs
                    continue;
                }
            }
            
            // Deduplicate and normalize
            $links = array_unique($links);
            return array_values($links);
            
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to parse HTML: " . $e->getMessage(), 0, $e);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);
        }
    }

    /**
     * Check if link should be skipped
     */
    private static function shouldSkipLink(string $href): bool {
        // Skip fragments
        if (self::startsWith($href, '#')) {
            return true;
        }

        // Skip non-http schemes
        $lowercaseHref = strtolower($href);
        $skipSchemes = ['mailto:', 'javascript:', 'tel:', 'fax:', 'data:', 'file:', 'ftp:'];
        
        foreach ($skipSchemes as $scheme) {
            if (self::startsWith($lowercaseHref, $scheme)) {
                return true;
            }
        }

        return false;
    }

    /** 
     * Resolve a possibly relative URL against a base URL.
     * @throws \InvalidArgumentException
     */
    public static function resolveUrl(string $href, string $base): ?string {
        if (empty($href) || empty($base)) {
            return null;
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
            $normalizedUri = $resolved->withFragment('');
            
            return (string)$normalizedUri;
            
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
    public static function getCanonical(string $html, string $baseUrl): ?string {
        if (empty($html)) {
            return null;
        }

        $dom = new \DOMDocument();
        
        // Suppress warnings for malformed HTML
        $previousValue = libxml_use_internal_errors(true);
        
        try {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
            
            $links = $dom->getElementsByTagName('link');
            
            foreach ($links as $link) {
                $rel = strtolower(trim($link->getAttribute('rel')));
                
                if ($rel === 'canonical') {
                    $href = trim($link->getAttribute('href'));
                    
                    if (empty($href)) {
                        continue;
                    }

                    try {
                        $resolved = self::resolveUrl($href, $baseUrl);
                        if ($resolved !== null) {
                            return $resolved;
                        }
                    } catch (\Throwable $e) {
                        // Invalid canonical URL, continue searching
                        continue;
                    }
                }
            }
            
            return null;
            
        } catch (\Throwable $e) {
            // If parsing fails, return null
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);
        }
    }

    /**
     * Extract meta robots directives
     */
    public static function getMetaRobots(string $html): array {
        if (empty($html)) {
            return [];
        }

        $dom = new \DOMDocument();
        $previousValue = libxml_use_internal_errors(true);
        
        try {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
            
            $metaTags = $dom->getElementsByTagName('meta');
            $directives = [];
            
            foreach ($metaTags as $meta) {
                $name = strtolower(trim($meta->getAttribute('name')));
                
                if ($name === 'robots' || $name === 'googlebot') {
                    $content = strtolower(trim($meta->getAttribute('content')));
                    if (!empty($content)) {
                        $parts = array_map('trim', explode(',', $content));
                        $directives = array_merge($directives, $parts);
                    }
                }
            }
            
            return array_unique($directives);
            
        } catch (\Throwable $e) {
            return [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);
        }
    }

    /**
     * PHP 7.4 compatible str_starts_with
     */
    private static function startsWith(string $haystack, string $needle): bool {
        if (function_exists('str_starts_with')) {
            return str_starts_with($haystack, $needle);
        }
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
