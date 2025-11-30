<?php

namespace IProDev\Sitemap\Filter;

class UrlFilter
{
    private array $excludePatterns = [];
    private array $includePatterns = [];
    private array $priorityRules = [];
    private bool $caseSensitive = false;

    public function __construct(array $config = [])
    {
        $this->excludePatterns = $config['exclude'] ?? [];
        $this->includePatterns = $config['include'] ?? [];
        $this->priorityRules = $config['priority'] ?? [];
        $this->caseSensitive = $config['case_sensitive'] ?? false;
    }

    /**
     * Add exclude pattern
     */
    public function addExcludePattern(string $pattern): void
    {
        $this->excludePatterns[] = $pattern;
    }

    /**
     * Add include pattern
     */
    public function addIncludePattern(string $pattern): void
    {
        $this->includePatterns[] = $pattern;
    }

    /**
     * Add priority rule
     */
    public function addPriorityRule(string $pattern, float $priority): void
    {
        $this->priorityRules[$pattern] = $priority;
    }

    /**
     * Check if URL should be crawled
     */
    public function shouldCrawl(string $url): bool
    {
        // First check include patterns (if any)
        if (!empty($this->includePatterns)) {
            $included = false;
            foreach ($this->includePatterns as $pattern) {
                if ($this->matchPattern($url, $pattern)) {
                    $included = true;
                    break;
                }
            }

            if (!$included) {
                return false;
            }
        }

        // Then check exclude patterns
        foreach ($this->excludePatterns as $pattern) {
            if ($this->matchPattern($url, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get priority for URL
     */
    public function getPriority(string $url): float
    {
        foreach ($this->priorityRules as $pattern => $priority) {
            if ($this->matchPattern($url, $pattern)) {
                return $priority;
            }
        }

        return 0.5; // Default priority
    }

    /**
     * Filter array of URLs
     */
    public function filterUrls(array $urls): array
    {
        return array_filter($urls, fn($url) => $this->shouldCrawl($url));
    }

    /**
     * Filter pages and add priority
     */
    public function filterPages(array $pages): array
    {
        $filtered = [];

        foreach ($pages as $page) {
            $url = $page['url'] ?? null;

            if (!$url || !$this->shouldCrawl($url)) {
                continue;
            }

            $page['priority'] = $this->getPriority($url);
            $filtered[] = $page;
        }

        return $filtered;
    }

    /**
     * Match URL against pattern
     */
    private function matchPattern(string $url, string $pattern): bool
    {
        if (!$this->caseSensitive) {
            $url = strtolower($url);
            $pattern = strtolower($pattern);
        }

        // Convert glob-style pattern to regex
        $regex = $this->globToRegex($pattern);

        return preg_match($regex, $url) === 1;
    }

    /**
     * Convert glob pattern to regex
     */
    private function globToRegex(string $pattern): string
    {
        // Escape special regex characters except * and ?
        $pattern = preg_quote($pattern, '/');

        // Convert glob wildcards to regex
        $pattern = str_replace('\*', '.*', $pattern);
        $pattern = str_replace('\?', '.', $pattern);

        // Don't anchor - allow pattern to match anywhere in URL
        return '/' . $pattern . '/';
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'exclude_patterns' => count($this->excludePatterns),
            'include_patterns' => count($this->includePatterns),
            'priority_rules' => count($this->priorityRules)
        ];
    }
}
