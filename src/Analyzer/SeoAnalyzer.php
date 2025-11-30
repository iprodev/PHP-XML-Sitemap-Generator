<?php

namespace IProDev\Sitemap\Analyzer;

class SeoAnalyzer
{
    private array $issues = [];
    private array $warnings = [];
    private array $recommendations = [];

    /**
     * Analyze page for SEO issues
     */
    public function analyze(string $url, string $html, int $statusCode, array $headers = []): array
    {
        $this->issues = [];
        $this->warnings = [];
        $this->recommendations = [];

        $this->checkStatusCode($statusCode);
        $this->checkTitle($html);
        $this->checkMetaDescription($html);
        $this->checkHeadings($html);
        $this->checkImages($html);
        $this->checkLinks($html, $url);
        $this->checkCanonical($html, $url);
        $this->checkRobots($html);
        $this->checkContentLength($html);
        $this->checkKeywordDensity($html);
        $this->checkMobileOptimization($html);
        $this->checkPageSpeed($headers);
        $this->checkStructuredData($html);

        return [
            'url' => $url,
            'score' => $this->calculateScore(),
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'recommendations' => $this->recommendations,
            'summary' => $this->getSummary()
        ];
    }

    /**
     * Check HTTP status code
     */
    private function checkStatusCode(int $code): void
    {
        if ($code >= 400) {
            $this->issues[] = [
                'type' => 'status_code',
                'severity' => 'critical',
                'message' => "HTTP {$code} error - Page not accessible"
            ];
        } elseif ($code >= 300 && $code < 400) {
            $this->warnings[] = [
                'type' => 'status_code',
                'severity' => 'warning',
                'message' => "HTTP {$code} redirect - May affect SEO"
            ];
        }
    }

    /**
     * Check title tag
     */
    private function checkTitle(string $html): void
    {
        if (!preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $this->issues[] = [
                'type' => 'title',
                'severity' => 'critical',
                'message' => 'Missing title tag'
            ];
            return;
        }

        $title = trim($matches[1]);
        $length = mb_strlen($title);

        if (empty($title)) {
            $this->issues[] = [
                'type' => 'title',
                'severity' => 'critical',
                'message' => 'Empty title tag'
            ];
        } elseif ($length < 30) {
            $this->warnings[] = [
                'type' => 'title',
                'severity' => 'warning',
                'message' => "Title too short ({$length} characters). Recommended: 30-60 characters"
            ];
        } elseif ($length > 60) {
            $this->warnings[] = [
                'type' => 'title',
                'severity' => 'warning',
                'message' => "Title too long ({$length} characters). May be truncated in search results"
            ];
        }
    }

    /**
     * Check meta description
     */
    private function checkMetaDescription(string $html): void
    {
        if (!preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            $this->warnings[] = [
                'type' => 'meta_description',
                'severity' => 'warning',
                'message' => 'Missing meta description'
            ];
            return;
        }

        $description = trim($matches[1]);
        $length = mb_strlen($description);

        if (empty($description)) {
            $this->warnings[] = [
                'type' => 'meta_description',
                'severity' => 'warning',
                'message' => 'Empty meta description'
            ];
        } elseif ($length < 120) {
            $this->recommendations[] = [
                'type' => 'meta_description',
                'message' => "Meta description could be longer ({$length} characters). Recommended: 120-160 characters"
            ];
        } elseif ($length > 160) {
            $this->warnings[] = [
                'type' => 'meta_description',
                'severity' => 'warning',
                'message' => "Meta description too long ({$length} characters). May be truncated"
            ];
        }
    }

    /**
     * Check heading structure
     */
    private function checkHeadings(string $html): void
    {
        // Check H1
        $h1Count = preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html);
        
        if ($h1Count === 0) {
            $this->issues[] = [
                'type' => 'headings',
                'severity' => 'critical',
                'message' => 'Missing H1 heading'
            ];
        } elseif ($h1Count > 1) {
            $this->warnings[] = [
                'type' => 'headings',
                'severity' => 'warning',
                'message' => "Multiple H1 tags found ({$h1Count}). Recommended: single H1 per page"
            ];
        }

        // Check heading hierarchy
        $headings = [];
        for ($i = 1; $i <= 6; $i++) {
            $headings["h{$i}"] = preg_match_all("/<h{$i}[^>]*>/is", $html);
        }

        if ($headings['h1'] > 0 && $headings['h2'] === 0) {
            $this->recommendations[] = [
                'type' => 'headings',
                'message' => 'Consider adding H2 headings for better content structure'
            ];
        }
    }

    /**
     * Check images
     */
    private function checkImages(string $html): void
    {
        preg_match_all('/<img[^>]+>/is', $html, $images);
        
        $imagesWithoutAlt = 0;
        foreach ($images[0] as $img) {
            if (!preg_match('/alt=["\'][^"\']*["\']/i', $img)) {
                $imagesWithoutAlt++;
            }
        }

        if ($imagesWithoutAlt > 0) {
            $this->warnings[] = [
                'type' => 'images',
                'severity' => 'warning',
                'message' => "{$imagesWithoutAlt} images without alt attributes"
            ];
        }
    }

    /**
     * Check internal/external links
     */
    private function checkLinks(string $html, string $baseUrl): void
    {
        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/is', $html, $matches);
        
        $internalLinks = 0;
        $externalLinks = 0;
        $brokenLinks = 0;
        
        $domain = parse_url($baseUrl, PHP_URL_HOST);
        
        foreach ($matches[1] as $link) {
            if (empty($link) || $link === '#') {
                continue;
            }
            
            if (strpos($link, $domain) !== false || strpos($link, '/') === 0) {
                $internalLinks++;
            } else {
                $externalLinks++;
            }
        }

        if ($internalLinks === 0) {
            $this->recommendations[] = [
                'type' => 'links',
                'message' => 'No internal links found. Consider adding internal links'
            ];
        }
    }

    /**
     * Check canonical URL
     */
    private function checkCanonical(string $html, string $url): void
    {
        if (!preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']/is', $html, $matches)) {
            $this->recommendations[] = [
                'type' => 'canonical',
                'message' => 'No canonical URL specified'
            ];
        }
    }

    /**
     * Check robots meta tag
     */
    private function checkRobots(string $html): void
    {
        if (preg_match('/<meta\s+name=["\']robots["\']\s+content=["\']([^"\']+)["\']/is', $html, $matches)) {
            $content = strtolower($matches[1]);
            
            if (strpos($content, 'noindex') !== false) {
                $this->issues[] = [
                    'type' => 'robots',
                    'severity' => 'critical',
                    'message' => 'Page is set to noindex - will not appear in search results'
                ];
            }
        }
    }

    /**
     * Check content length
     */
    private function checkContentLength(string $html): void
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $wordCount = str_word_count($text);

        if ($wordCount < 300) {
            $this->warnings[] = [
                'type' => 'content',
                'severity' => 'warning',
                'message' => "Low word count ({$wordCount} words). Recommended: at least 300 words"
            ];
        }
    }

    /**
     * Check keyword density (basic)
     */
    private function checkKeywordDensity(string $html): void
    {
        $text = strtolower(strip_tags($html));
        $words = str_word_count($text, 1);
        
        if (empty($words)) {
            return;
        }

        $wordFreq = array_count_values($words);
        arsort($wordFreq);
        
        $topWords = array_slice($wordFreq, 0, 5);
        $totalWords = count($words);
        
        foreach ($topWords as $word => $count) {
            $density = ($count / $totalWords) * 100;
            
            if ($density > 3 && strlen($word) > 4) { // Ignore common short words
                $this->recommendations[] = [
                    'type' => 'keyword_density',
                    'message' => "High density for '{$word}' (" . round($density, 2) . "%). May appear as keyword stuffing"
                ];
            }
        }
    }

    /**
     * Check mobile optimization
     */
    private function checkMobileOptimization(string $html): void
    {
        if (!preg_match('/<meta\s+name=["\']viewport["\']/is', $html)) {
            $this->warnings[] = [
                'type' => 'mobile',
                'severity' => 'warning',
                'message' => 'Missing viewport meta tag - may not be mobile-friendly'
            ];
        }
    }

    /**
     * Check page speed indicators
     */
    private function checkPageSpeed(array $headers): void
    {
        // Check for caching headers
        $hasCaching = false;
        foreach ($headers as $header => $value) {
            $headerLower = strtolower($header);
            if ($headerLower === 'cache-control' || $headerLower === 'expires') {
                $hasCaching = true;
                break;
            }
        }

        if (!$hasCaching) {
            $this->recommendations[] = [
                'type' => 'performance',
                'message' => 'No caching headers found. Consider adding cache-control headers'
            ];
        }

        // Check for compression
        $hasCompression = false;
        foreach ($headers as $header => $value) {
            if (strtolower($header) === 'content-encoding' && stripos($value, 'gzip') !== false) {
                $hasCompression = true;
                break;
            }
        }

        if (!$hasCompression) {
            $this->recommendations[] = [
                'type' => 'performance',
                'message' => 'No gzip compression detected. Consider enabling compression'
            ];
        }
    }

    /**
     * Check for structured data
     */
    private function checkStructuredData(string $html): void
    {
        $hasJsonLd = preg_match('/<script\s+type=["\']application\/ld\+json["\']/is', $html);
        $hasMicrodata = preg_match('/itemscope/is', $html);
        
        if (!$hasJsonLd && !$hasMicrodata) {
            $this->recommendations[] = [
                'type' => 'structured_data',
                'message' => 'No structured data found. Consider adding Schema.org markup'
            ];
        }
    }

    /**
     * Calculate SEO score
     */
    private function calculateScore(): int
    {
        $score = 100;
        
        // Deduct points for issues
        $score -= count($this->issues) * 10;
        $score -= count($this->warnings) * 5;
        
        return max(0, min(100, $score));
    }

    /**
     * Get summary
     */
    private function getSummary(): array
    {
        return [
            'total_issues' => count($this->issues),
            'total_warnings' => count($this->warnings),
            'total_recommendations' => count($this->recommendations)
        ];
    }
}
