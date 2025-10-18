<?php
namespace IProDev\Sitemap;

class RobotsTxt {
    /** @var array<int,string> */
    private array $disallows = [];
    
    /** @var array<int,string> */
    private array $allows = [];
    
    private bool $wildcardApplies = true;

    /**
     * Create RobotsTxt instance from URL
     */
    public static function fromUrl(string $baseUrl, Fetcher $fetcher): RobotsTxt {
        $parsedUrl = parse_url($baseUrl);
        
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            return new RobotsTxt();
        }

        $robotsUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port']) && !in_array($parsedUrl['port'], [80, 443])) {
            $robotsUrl .= ':' . $parsedUrl['port'];
        }
        $robotsUrl .= '/robots.txt';

        try {
            $response = $fetcher->get($robotsUrl);
            
            if ($response->getStatusCode() === 200) {
                $content = (string)$response->getBody();
                $robotsTxt = new RobotsTxt();
                $robotsTxt->parse($content);
                return $robotsTxt;
            }
        } catch (\Throwable $e) {
            // Silently fail - treat as no restrictions
        }

        return new RobotsTxt();
    }

    /**
     * Parse robots.txt content
     */
    private function parse(string $content): void {
        if (empty($content)) {
            return;
        }

        $lines = preg_split("/\r?\n/", $content);
        if ($lines === false) {
            return;
        }

        $currentApplies = false;

        foreach ($lines as $rawLine) {
            // Remove comments
            $line = trim(preg_replace('/\s*#.*$/', '', $rawLine) ?? '');
            
            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (empty($value)) {
                continue;
            }

            switch ($field) {
                case 'user-agent':
                    $userAgent = strtolower($value);
                    // Match * or specific bot names
                    $currentApplies = ($userAgent === '*' || 
                                      $userAgent === 'php-sitemap-generator' ||
                                      $userAgent === 'googlebot');
                    break;

                case 'disallow':
                    if ($currentApplies) {
                        // Empty disallow means allow all
                        if ($value !== '') {
                            $this->disallows[] = $value;
                        }
                    }
                    break;

                case 'allow':
                    if ($currentApplies && $value !== '') {
                        $this->allows[] = $value;
                    }
                    break;
            }
        }

        // Sort rules by length (longest first) for better matching
        usort($this->disallows, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        usort($this->allows, function($a, $b) {
            return strlen($b) - strlen($a);
        });
    }

    /**
     * Check if URL is allowed by robots.txt rules
     */
    public function isAllowed(string $url): bool {
        if (empty($this->disallows) && empty($this->allows)) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);
        
        if (!empty($query)) {
            $path .= '?' . $query;
        }

        // Check Allow rules first (they take precedence over Disallow)
        foreach ($this->allows as $rule) {
            if ($this->matchesRule($path, $rule)) {
                return true;
            }
        }

        // Then check Disallow rules
        foreach ($this->disallows as $rule) {
            if ($this->matchesRule($path, $rule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if path matches a robots.txt rule (supports wildcards)
     */
    private function matchesRule(string $path, string $rule): bool {
        // Empty rule matches nothing
        if ($rule === '') {
            return false;
        }

        // Handle wildcards (* and $)
        $pattern = preg_quote($rule, '/');
        
        // Replace wildcards
        $pattern = str_replace('\*', '.*', $pattern);
        
        // $ at end means exact match
        if ($this->endsWith($rule, '$')) {
            $pattern = rtrim($pattern, '\$') . '$';
        }

        // Match from beginning
        $pattern = '/^' . $pattern . '/';

        return preg_match($pattern, $path) === 1;
    }

    /**
     * Get all disallow rules
     * @return string[]
     */
    public function getDisallows(): array {
        return $this->disallows;
    }

    /**
     * Get all allow rules
     * @return string[]
     */
    public function getAllows(): array {
        return $this->allows;
    }

    /**
     * PHP 7.4 compatible str_ends_with
     */
    private function endsWith(string $haystack, string $needle): bool {
        if (function_exists('str_ends_with')) {
            return str_ends_with($haystack, $needle);
        }
        $length = strlen($needle);
        return $length === 0 || substr($haystack, -$length) === $needle;
    }
}
