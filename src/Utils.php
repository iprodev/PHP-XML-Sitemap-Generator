<?php

namespace IProDev\Sitemap;

class Utils
{
    /**
     * Normalize URL by removing trailing slash
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        return rtrim($url, '/');
    }

    /**
     * Format bytes to human readable format
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format duration in seconds to human readable format
     */
    public static function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        return $minutes . 'm ' . round($secs, 0) . 's';
    }

    /**
     * Validate URL
     */
    public static function isValidUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Get domain from URL
     */
    public static function getDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    /**
     * Calculate progress percentage
     */
    public static function calculateProgress(int $current, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        return min(100.0, ($current / $total) * 100);
    }

    /**
     * Create a progress bar string
     */
    public static function progressBar(int $current, int $total, int $width = 50): string
    {
        $percentage = self::calculateProgress($current, $total);
        $filled = (int)(($percentage / 100) * $width);
        $empty = $width - $filled;

        $bar = '[' . str_repeat('=', $filled) . str_repeat(' ', $empty) . ']';
        return sprintf('%s %d/%d (%.1f%%)', $bar, $current, $total, $percentage);
    }

    /**
     * Get memory usage in human readable format
     */
    public static function getMemoryUsage(): string
    {
        return self::formatBytes(memory_get_usage(true));
    }

    /**
     * Get peak memory usage in human readable format
     */
    public static function getPeakMemoryUsage(): string
    {
        return self::formatBytes(memory_get_peak_usage(true));
    }

    /**
     * Ensure directory exists and is writable
     */
    public static function ensureDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return false;
            }
        }

        return is_writable($path);
    }

    /**
     * Count words in any script (Latin, Persian/Arabic, Cyrillic, ...).
     *
     * str_word_count() only understands Latin letters and returns 0 for e.g.
     * Persian text. Zero-width (non-)joiners count as part of a word.
     */
    public static function countWords(string $text): int
    {
        $count = preg_match_all("/[\\p{L}\\p{N}][\\p{L}\\p{M}\\p{N}\\x{200C}\\x{200D}\\x{2019}'_-]*/u", $text);
        return $count === false ? 0 : $count;
    }

    /**
     * Remove credentials from a URL (user:password@ part).
     * Strings that are not URLs are returned unchanged.
     */
    public static function redactUrl(string $url): string
    {
        // Greedy up to the last "@" before the first "/" so passwords containing "@" are covered
        return preg_replace('#^([a-z][a-z0-9+.\-]*://)[^/]*@#i', '$1***@', $url) ?? $url;
    }

    /**
     * Return a copy of a configuration array that is safe to log, store or send
     * to third parties: passwords, tokens and credentials are masked.
     *
     * - keys that look like secrets (password, secret, token, api key, auth, credential) are replaced
     * - DSNs lose their password (password=... and user:pass@)
     * - webhook URLs keep only scheme and host (their path or query often is the secret)
     * - other URLs lose their user:password@ part
     */
    public static function redactConfig(array $config): array
    {
        $redacted = [];

        foreach ($config as $key => $value) {
            $name = strtolower((string)$key);

            if (is_array($value)) {
                $redacted[$key] = self::redactConfig($value);
                continue;
            }

            if (!is_string($value) || $value === '') {
                $redacted[$key] = $value;
                continue;
            }

            if (preg_match('/pass(word)?|pwd|secret|token|api[_-]?key|auth|credential/', $name)) {
                $redacted[$key] = '[REDACTED]';
            } elseif (str_contains($name, 'dsn')) {
                $value = preg_replace('/((?:password|pwd)\s*=)[^;]*/i', '$1***', $value) ?? $value;
                $redacted[$key] = self::redactUrl($value);
            } elseif (str_contains($name, 'webhook')) {
                $parts = parse_url($value);
                $redacted[$key] = isset($parts['scheme'], $parts['host'])
                    ? $parts['scheme'] . '://' . $parts['host'] . '/[REDACTED]'
                    : '[REDACTED]';
            } else {
                $redacted[$key] = self::redactUrl($value);
            }
        }

        return $redacted;
    }

    /**
     * Clean URL by removing query parameters and fragments
     */
    public static function cleanUrl(string $url, bool $removeQuery = true): string
    {
        $parsed = parse_url($url);

        if (!$parsed) {
            return $url;
        }

        $clean = ($parsed['scheme'] ?? 'http') . '://';

        if (isset($parsed['host'])) {
            $clean .= $parsed['host'];
        }

        if (isset($parsed['port']) && !in_array($parsed['port'], [80, 443])) {
            $clean .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            $clean .= $parsed['path'];
        }

        if (!$removeQuery && isset($parsed['query'])) {
            $clean .= '?' . $parsed['query'];
        }

        return $clean;
    }
}
