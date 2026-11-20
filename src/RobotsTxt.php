<?php

namespace IProDev\Sitemap;

class RobotsTxt
{
    /**
     * Product token this crawler identifies itself with (see Fetcher's User-Agent)
     */
    public const USER_AGENT_TOKEN = 'php-sitemap-generator';

    /** @var array<int,string> */
    private array $disallows = [];

    /** @var array<int,string> */
    private array $allows = [];

    /**
     * Create RobotsTxt instance from robots.txt content
     */
    public static function fromString(string $content): RobotsTxt
    {
        $robotsTxt = new RobotsTxt();
        $robotsTxt->parse($content);
        return $robotsTxt;
    }

    /**
     * Create RobotsTxt instance from URL
     */
    public static function fromUrl(string $baseUrl, Fetcher $fetcher): RobotsTxt
    {
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
     * Parse robots.txt content (RFC 9309).
     *
     * A group starts with one or more consecutive User-agent lines followed by
     * its rules. Only the groups that name this crawler are used; if there are
     * none, the groups for "*" are used. Rules of several matching groups are
     * combined.
     */
    private function parse(string $content): void
    {
        $this->disallows = [];
        $this->allows = [];

        if ($content === '') {
            return;
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        if ($lines === false) {
            return;
        }

        /** @var array<int, array{agents: string[], allow: string[], disallow: string[]}> $groups */
        $groups = [];
        $current = null;
        $groupHasRules = false;

        foreach ($lines as $rawLine) {
            // Remove comments
            $line = trim(preg_replace('/#.*$/', '', $rawLine) ?? '');

            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            switch ($field) {
                case 'user-agent':
                    // A User-agent line after rules starts a new group;
                    // consecutive User-agent lines share the same group.
                    if ($current === null || $groupHasRules) {
                        $groups[] = ['agents' => [], 'allow' => [], 'disallow' => []];
                        $current = count($groups) - 1;
                        $groupHasRules = false;
                    }
                    if ($value !== '') {
                        $groups[$current]['agents'][] = strtolower($value);
                    }
                    break;

                case 'allow':
                case 'disallow':
                    if ($current === null) {
                        // Rule outside of any group: ignore
                        break;
                    }
                    $groupHasRules = true;
                    // An empty value is a valid rule that matches nothing
                    if ($value !== '') {
                        $groups[$current][$field][] = $value;
                    }
                    break;
            }
        }

        $selected = array_filter(
            $groups,
            fn(array $group) => in_array(self::USER_AGENT_TOKEN, $group['agents'], true)
        );
        if (empty($selected)) {
            $selected = array_filter($groups, fn(array $group) => in_array('*', $group['agents'], true));
        }

        foreach ($selected as $group) {
            $this->allows = array_merge($this->allows, $group['allow']);
            $this->disallows = array_merge($this->disallows, $group['disallow']);
        }

        $this->allows = array_values(array_unique($this->allows));
        $this->disallows = array_values(array_unique($this->disallows));

        // Sort rules by length (longest first)
        usort($this->disallows, fn($a, $b) => strlen($b) - strlen($a));
        usort($this->allows, fn($a, $b) => strlen($b) - strlen($a));
    }

    /**
     * Check if URL is allowed by robots.txt rules.
     *
     * The most specific (longest) matching rule wins; when an Allow and a
     * Disallow rule are equally specific, Allow wins (RFC 9309, section 2.2.2).
     */
    public function isAllowed(string $url): bool
    {
        if (empty($this->disallows) && empty($this->allows)) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = '/';
        }
        $query = parse_url($url, PHP_URL_QUERY);

        if (!empty($query)) {
            $path .= '?' . $query;
        }

        $longestAllow = $this->longestMatch($path, $this->allows);
        $longestDisallow = $this->longestMatch($path, $this->disallows);

        if ($longestDisallow === -1) {
            return true;
        }

        return $longestAllow >= $longestDisallow;
    }

    /**
     * Length of the longest rule matching the path, or -1 if none matches
     *
     * @param string[] $rules
     */
    private function longestMatch(string $path, array $rules): int
    {
        $longest = -1;
        foreach ($rules as $rule) {
            if (strlen($rule) > $longest && $this->matchesRule($path, $rule)) {
                $longest = strlen($rule);
            }
        }
        return $longest;
    }

    /**
     * Check if path matches a robots.txt rule (supports wildcards)
     */
    private function matchesRule(string $path, string $rule): bool
    {
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
    public function getDisallows(): array
    {
        return $this->disallows;
    }

    /**
     * Get all allow rules
     * @return string[]
     */
    public function getAllows(): array
    {
        return $this->allows;
    }

    /**
     * PHP 7.4 compatible str_ends_with
     */
    private function endsWith(string $haystack, string $needle): bool
    {
        if (function_exists('str_ends_with')) {
            return str_ends_with($haystack, $needle);
        }
        $length = strlen($needle);
        return $length === 0 || substr($haystack, -$length) === $needle;
    }
}
