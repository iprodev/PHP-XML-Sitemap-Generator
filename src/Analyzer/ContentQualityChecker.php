<?php

namespace IProDev\Sitemap\Analyzer;

class ContentQualityChecker
{
    /**
     * Check for duplicate content
     */
    public function findDuplicates(array $pages): array
    {
        $duplicates = [];
        $hashes = [];

        foreach ($pages as $page) {
            $hash = $page['content_hash'] ?? null;

            if (!$hash) {
                continue;
            }

            if (isset($hashes[$hash])) {
                $duplicates[] = [
                    'original' => $hashes[$hash],
                    'duplicate' => $page['url'],
                    'hash' => $hash
                ];
            } else {
                $hashes[$hash] = $page['url'];
            }
        }

        return $duplicates;
    }

    /**
     * Find broken links
     */
    public function findBrokenLinks(array $pages): array
    {
        $broken = [];

        foreach ($pages as $page) {
            // Crawler records use "status", database rows "status_code"
            $status = $page['status_code'] ?? $page['status'] ?? null;
            if (is_numeric($status) && (int)$status >= 400) {
                $broken[] = [
                    'url' => $page['url'],
                    'status_code' => (int)$status,
                    'error' => $this->getErrorMessage((int)$status)
                ];
            }
        }

        return $broken;
    }

    /**
     * Check for thin content
     */
    public function findThinContent(array $pages, int $minWords = 300): array
    {
        $thin = [];

        foreach ($pages as $page) {
            if (isset($page['word_count']) && $page['word_count'] < $minWords) {
                $thin[] = [
                    'url' => $page['url'],
                    'word_count' => $page['word_count'],
                    'recommended' => $minWords
                ];
            }
        }

        return $thin;
    }

    /**
     * Find missing meta descriptions
     */
    public function findMissingMetaDescriptions(array $pages): array
    {
        $missing = [];

        foreach ($pages as $page) {
            if (empty($page['meta_description'])) {
                $missing[] = $page['url'];
            }
        }

        return $missing;
    }

    /**
     * Find missing titles
     */
    public function findMissingTitles(array $pages): array
    {
        $missing = [];

        foreach ($pages as $page) {
            if (empty($page['title'])) {
                $missing[] = $page['url'];
            }
        }

        return $missing;
    }

    /**
     * Check for long load times
     */
    public function findSlowPages(array $pages, float $maxTime = 3.0): array
    {
        $slow = [];

        foreach ($pages as $page) {
            if (isset($page['response_time']) && $page['response_time'] > $maxTime) {
                $slow[] = [
                    'url' => $page['url'],
                    'response_time' => round($page['response_time'], 2),
                    'threshold' => $maxTime
                ];
            }
        }

        return $slow;
    }

    /**
     * Check for large page sizes
     */
    public function findLargePages(array $pages, int $maxSize = 1048576): array // 1MB default
    {
        $large = [];

        foreach ($pages as $page) {
            if (isset($page['content_size']) && $page['content_size'] > $maxSize) {
                $large[] = [
                    'url' => $page['url'],
                    'size' => $page['content_size'],
                    'size_formatted' => $this->formatBytes($page['content_size']),
                    'threshold' => $this->formatBytes($maxSize)
                ];
            }
        }

        return $large;
    }

    /**
     * Find noindex pages
     */
    public function findNoindexPages(array $pages): array
    {
        $noindex = [];

        foreach ($pages as $page) {
            if (isset($page['is_noindex']) && $page['is_noindex']) {
                $noindex[] = $page['url'];
            }
        }

        return $noindex;
    }

    /**
     * Generate comprehensive quality report
     */
    public function generateReport(array $pages): array
    {
        $findings = $this->collectFindings($pages);

        return [
            'total_pages' => count($pages),
            'duplicates' => [
                'count' => count($findings['duplicates']),
                'details' => $findings['duplicates']
            ],
            'broken_links' => [
                'count' => count($findings['broken']),
                'details' => $findings['broken']
            ],
            'thin_content' => [
                'count' => count($findings['thin']),
                'details' => $findings['thin']
            ],
            'missing_meta' => [
                'descriptions' => count($findings['missing_descriptions']),
                'titles' => count($findings['missing_titles'])
            ],
            'performance' => [
                'slow_pages' => count($findings['slow']),
                'large_pages' => count($findings['large'])
            ],
            'noindex_pages' => count($findings['noindex']),
            'quality_score' => $this->scoreFindings(count($pages), $findings)
        ];
    }

    /**
     * Run every check once
     */
    private function collectFindings(array $pages): array
    {
        return [
            'duplicates' => $this->findDuplicates($pages),
            'broken' => $this->findBrokenLinks($pages),
            'thin' => $this->findThinContent($pages),
            'missing_descriptions' => $this->findMissingMetaDescriptions($pages),
            'missing_titles' => $this->findMissingTitles($pages),
            'slow' => $this->findSlowPages($pages),
            'large' => $this->findLargePages($pages),
            'noindex' => $this->findNoindexPages($pages),
        ];
    }

    /**
     * Calculate overall quality score (0-100)
     */
    private function scoreFindings(int $total, array $findings): int
    {
        if ($total === 0) {
            return 0;
        }

        $issues = count($findings['duplicates']) * 2
            + count($findings['broken']) * 3
            + count($findings['thin'])
            + count($findings['missing_descriptions']) * 0.5
            + count($findings['missing_titles']) * 2
            + count($findings['slow']) * 0.5
            + count($findings['noindex']);

        $score = 100 - (($issues / $total) * 50);

        return max(0, min(100, (int)$score));
    }

    /**
     * Get error message for status code
     */
    private function getErrorMessage(int $code): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            410 => 'Gone',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];

        return $messages[$code] ?? 'Unknown Error';
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
