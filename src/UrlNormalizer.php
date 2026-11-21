<?php

namespace IProDev\Sitemap;

/**
 * Normalizes URLs so that trivially different spellings of the same page are
 * crawled and listed once: lowercase scheme and host, no default port, no
 * fragment, "/" for an empty path, and no tracking parameters.
 *
 * Paths (including trailing slashes) and the order of the remaining query
 * parameters are kept, because sites may treat them as different pages.
 */
final class UrlNormalizer
{
    public const TRACKING_PARAMETERS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'gclid', 'dclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'yclid', 'mc_cid', 'mc_eid', 'igshid',
    ];

    /**
     * @param string[] $stripParameters query parameters to remove (case-insensitive)
     */
    public static function normalize(string $url, array $stripParameters = self::TRACKING_PARAMETERS): string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        $port = $parts['port'] ?? null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $userInfo = '';
        if (isset($parts['user'])) {
            $userInfo = $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@';
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = '/';
        }

        $query = $parts['query'] ?? '';
        if ($query !== '' && !empty($stripParameters)) {
            $strip = array_map('strtolower', $stripParameters);
            $pairs = array_filter(
                explode('&', $query),
                static function (string $pair) use ($strip): bool {
                    if ($pair === '') {
                        return false;
                    }
                    $name = strtolower(urldecode(explode('=', $pair, 2)[0]));
                    return !in_array($name, $strip, true);
                }
            );
            $query = implode('&', $pairs);
        }

        return $scheme . '://' . $userInfo . $host . ($port !== null ? ':' . $port : '') . $path
            . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Lowercase host of a URL, or null
     */
    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
