<?php

namespace IProDev\Sitemap\Scheduler;

class CronScheduler
{
    /**
     * bin/sitemap options that take a value
     */
    public const VALUE_OPTIONS = [
        'url', 'out', 'concurrency', 'max-pages', 'max-depth', 'public-base',
        'rate-limit', 'delay', 'cache-driver', 'cache-ttl', 'checkpoint-interval',
        'db-dsn', 'exclude', 'include', 'priority-rules', 'chrome-path',
        'wait-for-ajax', 'proxy', 'proxy-file', 'webhook-url',
    ];

    /**
     * bin/sitemap options that are on/off flags
     */
    public const FLAG_OPTIONS = [
        'cache-enabled', 'resume', 'db-enabled', 'detect-changes', 'only-changed',
        'seo-analysis', 'check-quality', 'find-duplicates', 'find-broken-links',
        'image-sitemap', 'video-sitemap', 'news-sitemap', 'enable-javascript',
        'rotate-proxies', 'notify-on-complete', 'notify-on-error', 'verbose',
    ];

    /**
     * Config keys whose kebab-case form differs from the option name
     */
    private const OPTION_ALIASES = [
        'enable-java-script' => 'enable-javascript',
    ];

    private string $scheduleFile;
    private array $schedules = [];

    public function __construct(string $scheduleFile = './schedules.json')
    {
        $this->scheduleFile = $scheduleFile;
        $this->loadSchedules();
    }

    /**
     * Add a scheduled crawl
     */
    public function addSchedule(string $name, array $config): void
    {
        $this->schedules[$name] = [
            'name' => $name,
            'config' => $config,
            'cron_expression' => $config['schedule'] ?? 'daily',
            'last_run' => null,
            'next_run' => $this->calculateNextRun($config['schedule'] ?? 'daily'),
            'enabled' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->saveSchedules();
    }

    /**
     * Remove a schedule
     */
    public function removeSchedule(string $name): bool
    {
        if (isset($this->schedules[$name])) {
            unset($this->schedules[$name]);
            $this->saveSchedules();
            return true;
        }
        return false;
    }

    /**
     * Enable/disable schedule
     */
    public function toggleSchedule(string $name, bool $enabled): void
    {
        if (isset($this->schedules[$name])) {
            $this->schedules[$name]['enabled'] = $enabled;
            $this->saveSchedules();
        }
    }

    /**
     * Get schedules that should run now
     */
    public function getDueSchedules(): array
    {
        $due = [];
        $now = time();

        foreach ($this->schedules as $name => $schedule) {
            if (!$schedule['enabled']) {
                continue;
            }

            $nextRun = strtotime($schedule['next_run']);
            if ($nextRun <= $now) {
                $due[] = $schedule;
            }
        }

        return $due;
    }

    /**
     * Mark schedule as run
     */
    public function markAsRun(string $name): void
    {
        if (isset($this->schedules[$name])) {
            $this->schedules[$name]['last_run'] = date('Y-m-d H:i:s');
            $this->schedules[$name]['next_run'] = $this->calculateNextRun(
                $this->schedules[$name]['cron_expression']
            );
            $this->saveSchedules();
        }
    }

    /**
     * Get all schedules
     */
    public function getSchedules(): array
    {
        return $this->schedules;
    }

    /**
     * Calculate next run time
     */
    private function calculateNextRun(string $expression): string
    {
        $now = time();

        switch ($expression) {
            case 'hourly':
                $next = strtotime('+1 hour', $now);
                break;

            case 'daily':
                $next = strtotime('tomorrow 00:00', $now);
                break;

            case 'weekly':
                $next = strtotime('next monday 00:00', $now);
                break;

            case 'monthly':
                $next = strtotime('first day of next month 00:00', $now);
                break;

            default:
                // Try to parse as cron expression (simplified)
                $next = $this->parseCronExpression($expression);
                if (!$next) {
                    $next = strtotime('+1 day', $now);
                }
        }

        return date('Y-m-d H:i:s', $next);
    }

    /**
     * Parse cron expression (simplified)
     * Format: minute hour day month weekday
     * Example: "0 2 * * *" = Every day at 2:00 AM
     */
    private function parseCronExpression(string $expression): ?int
    {
        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            return null;
        }

        list($minute, $hour, $day, $month, $weekday) = $parts;

        $now = time();
        $currentHour = (int)date('H', $now);
        $currentMinute = (int)date('i', $now);

        // Simple case: specific time each day (e.g., "0 2 * * *")
        if ($day === '*' && $month === '*' && $weekday === '*') {
            $targetHour = (int)$hour;
            $targetMinute = (int)$minute;

            if (
                $targetHour > $currentHour ||
                ($targetHour === $currentHour && $targetMinute > $currentMinute)
            ) {
                // Today
                return strtotime(sprintf('%02d:%02d:00', $targetHour, $targetMinute));
            } else {
                // Tomorrow
                return strtotime(sprintf('tomorrow %02d:%02d:00', $targetHour, $targetMinute));
            }
        }

        // For more complex expressions, use next day as fallback
        return strtotime('+1 day', $now);
    }

    /**
     * Load schedules from file
     */
    private function loadSchedules(): void
    {
        if (file_exists($this->scheduleFile)) {
            $content = file_get_contents($this->scheduleFile);
            $this->schedules = json_decode($content, true) ?? [];
        }
    }

    /**
     * Save schedules to file
     */
    private function saveSchedules(): void
    {
        $dir = dirname($this->scheduleFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->scheduleFile,
            json_encode($this->schedules, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Turn a schedule's config into bin/sitemap arguments.
     *
     * The result is meant for proc_open() with an argument array, so no shell
     * is involved and values are never interpreted as shell syntax. Only known
     * options are passed on; keys may be kebab-case, snake_case or camelCase
     * (e.g. "max-pages", "max_pages" or "maxPages").
     *
     * @param array<string, mixed> $config
     * @param string[]|null $skipped receives the config keys that were ignored
     * @return string[] arguments such as "--url=https://example.com" or "--verbose"
     */
    public static function buildArguments(array $config, ?array &$skipped = null): array
    {
        $skipped = [];
        $args = [];

        foreach ($config as $key => $value) {
            if ($key === 'schedule') {
                continue;
            }

            $option = self::normalizeOptionName((string)$key);

            if (in_array($option, self::FLAG_OPTIONS, true)) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    $args[] = '--' . $option;
                }
                continue;
            }

            if (!in_array($option, self::VALUE_OPTIONS, true)) {
                $skipped[] = (string)$key;
                continue;
            }

            $stringValue = self::optionValueToString($option, $value);
            if ($stringValue === null) {
                $skipped[] = (string)$key;
                continue;
            }

            $args[] = '--' . $option . '=' . $stringValue;
        }

        return $args;
    }

    private static function normalizeOptionName(string $key): string
    {
        $option = strtolower(preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $key) ?? $key);
        $option = str_replace('_', '-', $option);

        return self::OPTION_ALIASES[$option] ?? $option;
    }

    private static function optionValueToString(string $option, mixed $value): ?string
    {
        if (is_array($value)) {
            if ($option === 'priority-rules') {
                $encoded = json_encode($value);
                return $encoded === false ? null : $encoded;
            }
            foreach ($value as $item) {
                if (!is_scalar($item)) {
                    return null;
                }
            }
            $value = implode(',', $value);
        } elseif (is_bool($value) || !is_scalar($value)) {
            return null;
        }

        $value = (string)$value;

        // NUL bytes cannot be passed as process arguments
        return str_contains($value, "\0") ? null : $value;
    }

    /**
     * Generate cron command
     */
    public function generateCronCommand(string $phpBinary = 'php', string $scriptPath = 'bin/sitemap'): string
    {
        $schedulerScript = __DIR__ . '/../../bin/scheduler';
        return "* * * * * {$phpBinary} {$schedulerScript} > /dev/null 2>&1";
    }
}
