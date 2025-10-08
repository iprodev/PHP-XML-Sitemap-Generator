<?php
namespace IProDev\Sitemap;

class Utils {
    public static function normalizeUrl(string $url): string {
        return rtrim($url, '/');
    }
}
