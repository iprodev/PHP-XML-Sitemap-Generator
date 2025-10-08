<?php
namespace IProDev\Sitemap;

class RobotsTxt {
    /** @var array<int,string> */
    private array $disallows = [];
    private bool $wildcardApplies = true; // only parse User-agent: * rules for simplicity

    public static function fromUrl(string $baseUrl, Fetcher $fetcher): RobotsTxt {
        $u = parse_url($baseUrl);
        if (!$u || !isset($u['scheme']) || !isset($u['host'])) {
            return new RobotsTxt();
        }
        $robotsUrl = $u['scheme'] . '://' . $u['host'] . '/robots.txt';
        try {
            $resp = $fetcher->get($robotsUrl);
            if ($resp->getStatusCode() === 200) {
                $content = (string)$resp->getBody();
                $r = new RobotsTxt();
                $r->parse($content);
                return $r;
            }
        } catch (\Throwable $e) {
            // ignore network failures; treat as no restrictions
        }
        return new RobotsTxt();
    }

    private function parse(string $content): void {
        $lines = preg_split("/\r?\n/", $content);
        $currentApplies = false;
        foreach ($lines as $raw) {
            $line = trim(preg_replace('/\s*#.*$/', '', $raw) ?? '');
            if ($line === '') continue;
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) continue;
            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($field === 'user-agent') {
                $ua = strtolower($value);
                $currentApplies = ($ua === '*' || $ua === 'php-sitemap-generator');
            } elseif ($currentApplies && $field === 'disallow') {
                $this->disallows[] = $value;
            }
        }
    }

    public function isAllowed(string $url): bool {
        if (empty($this->disallows)) return true;
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        foreach ($this->disallows as $rule) {
            if ($rule === '') continue;
            if (str_starts_with($path, $rule)) return false;
        }
        return true;
    }
}
