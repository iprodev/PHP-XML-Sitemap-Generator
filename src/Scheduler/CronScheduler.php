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

    /**
     * Named schedules. "weekly" keeps this tool's historic meaning (Monday 00:00);
     * "@weekly" is the standard cron alias (Sunday 00:00).
     */
    private const EXPRESSION_ALIASES = [
        'hourly' => '0 * * * *', '@hourly' => '0 * * * *',
        'daily' => '0 0 * * *', '@daily' => '0 0 * * *', '@midnight' => '0 0 * * *',
        'weekly' => '0 0 * * 1', '@weekly' => '0 0 * * 0',
        'monthly' => '0 0 1 * *', '@monthly' => '0 0 1 * *',
        'yearly' => '0 0 1 1 *', '@yearly' => '0 0 1 1 *', '@annually' => '0 0 1 1 *',
    ];

    private const MONTH_NAMES = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const DAY_NAMES = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    private string $scheduleFile;
    private array $schedules = [];

    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(string $scheduleFile = './schedules.json')
    {
        $this->scheduleFile = $scheduleFile;
        $this->loadSchedules();
    }

    public function __destruct()
    {
        $this->unlock();
    }

    /**
     * Add a scheduled crawl. $config['schedule'] is a 5-field cron expression or
     * one of hourly, daily, weekly, monthly, yearly (or @hourly, @daily, ...).
     *
     * @throws \InvalidArgumentException for an invalid schedule expression
     */
    public function addSchedule(string $name, array $config): void
    {
        $expression = $config['schedule'] ?? 'daily';

        $this->schedules[$name] = [
            'name' => $name,
            'config' => $config,
            'cron_expression' => $expression,
            'last_run' => null,
            'next_run' => $this->calculateNextRun($expression),
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

        foreach ($this->schedules as $schedule) {
            if (empty($schedule['enabled']) || empty($schedule['next_run'])) {
                continue;
            }

            $nextRun = strtotime($schedule['next_run']);
            if ($nextRun !== false && $nextRun <= $now) {
                $due[] = $schedule;
            }
        }

        return $due;
    }

    /**
     * Mark a schedule as started and move next_run to the next matching time.
     *
     * Call this *before* running the crawl: otherwise a crawl that takes longer than
     * the cron interval, or that fails, would be started again on every scheduler run.
     *
     * @throws \InvalidArgumentException when the schedule's expression is invalid
     */
    public function markAsRun(string $name): void
    {
        if (isset($this->schedules[$name])) {
            $this->schedules[$name]['last_run'] = date('Y-m-d H:i:s');
            $this->schedules[$name]['next_run'] = $this->calculateNextRun(
                (string)($this->schedules[$name]['cron_expression'] ?? 'daily')
            );
            $this->saveSchedules();
        }
    }

    /**
     * Record how a run ended
     */
    public function recordResult(string $name, int $exitCode): void
    {
        if (isset($this->schedules[$name])) {
            $this->schedules[$name]['last_exit_code'] = $exitCode;
            $this->schedules[$name]['last_finished'] = date('Y-m-d H:i:s');
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
     * Take an exclusive, non-blocking lock so only one scheduler process runs at a time
     *
     * @return bool false if another process holds the lock
     */
    public function lock(): bool
    {
        if ($this->lockHandle !== null) {
            return true;
        }

        $this->ensureDirectory();
        $handle = fopen($this->scheduleFile . '.lock', 'c');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open lock file {$this->scheduleFile}.lock");
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        $this->lockHandle = $handle;
        return true;
    }

    public function unlock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /**
     * Calculate next run time
     */
    private function calculateNextRun(string $expression): string
    {
        return self::nextRunTime($expression, new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    /**
     * First minute after $after that matches a cron expression.
     *
     * Supports the standard 5 fields (minute hour day-of-month month day-of-week)
     * with "*", lists, ranges, steps and month/day names, plus the named schedules.
     * As in cron, when both day-of-month and day-of-week are restricted, a day
     * matching either of them matches.
     *
     * @throws \InvalidArgumentException for an invalid or impossible expression
     */
    public static function nextRunTime(string $expression, \DateTimeInterface $after): \DateTimeImmutable
    {
        $fields = self::parseExpression($expression);

        $time = \DateTimeImmutable::createFromInterface($after);
        $time = $time->setTime((int)$time->format('G'), (int)$time->format('i'))->modify('+1 minute');
        $limit = $after->getTimestamp() + 5 * 366 * 86400;

        while ($time->getTimestamp() <= $limit) {
            if (!isset($fields['month'][(int)$time->format('n')])) {
                $time = $time->modify('first day of next month')->setTime(0, 0);
                continue;
            }
            if (!self::dayMatches($fields, $time)) {
                $time = $time->modify('+1 day')->setTime(0, 0);
                continue;
            }
            if (!isset($fields['hour'][(int)$time->format('G')])) {
                $time = $time->modify('+1 hour');
                $time = $time->setTime((int)$time->format('G'), 0);
                continue;
            }
            if (!isset($fields['minute'][(int)$time->format('i')])) {
                $time = $time->modify('+1 minute');
                continue;
            }

            return $time;
        }

        throw new \InvalidArgumentException("Cron expression '{$expression}' never matches");
    }

    private static function dayMatches(array $fields, \DateTimeImmutable $time): bool
    {
        $domMatches = isset($fields['dom'][(int)$time->format('j')]);
        $dowMatches = isset($fields['dow'][(int)$time->format('w')]);

        if ($fields['dom_restricted'] && $fields['dow_restricted']) {
            return $domMatches || $dowMatches;
        }

        return $domMatches && $dowMatches;
    }

    /**
     * @return array{minute: array, hour: array, dom: array, month: array, dow: array,
     *               dom_restricted: bool, dow_restricted: bool}
     */
    private static function parseExpression(string $expression): array
    {
        $normalized = strtolower(trim($expression));
        $normalized = self::EXPRESSION_ALIASES[$normalized] ?? $normalized;

        $parts = preg_split('/\s+/', $normalized) ?: [];
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException(
                "Invalid cron expression '{$expression}': expected 5 fields (minute hour day month weekday)"
            );
        }

        [$minute, $hour, $dom, $month, $dow] = $parts;

        $dowValues = self::parseField($dow, 0, 7, self::DAY_NAMES, $expression);
        if (isset($dowValues[7])) {
            $dowValues[0] = true; // 7 is Sunday too
        }

        return [
            'minute' => self::parseField($minute, 0, 59, [], $expression),
            'hour' => self::parseField($hour, 0, 23, [], $expression),
            'dom' => self::parseField($dom, 1, 31, [], $expression),
            'month' => self::parseField($month, 1, 12, self::MONTH_NAMES, $expression),
            'dow' => $dowValues,
            'dom_restricted' => !str_starts_with($dom, '*'),
            'dow_restricted' => !str_starts_with($dow, '*'),
        ];
    }

    /**
     * @param array<string, int> $names
     * @return array<int, true>
     */
    private static function parseField(string $field, int $min, int $max, array $names, string $expression): array
    {
        $values = [];

        foreach (explode(',', $field) as $part) {
            if (!preg_match('/^(\*|[a-z0-9]+(?:-[a-z0-9]+)?)(?:\/(\d+))?$/', $part, $m)) {
                throw new \InvalidArgumentException("Invalid cron expression '{$expression}': bad field '{$field}'");
            }

            $step = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 1;
            if ($m[1] === '*') {
                [$start, $end] = [$min, $max];
            } else {
                $range = explode('-', $m[1]);
                $start = self::fieldValue($range[0], $names, $expression);
                $end = isset($range[1])
                    ? self::fieldValue($range[1], $names, $expression)
                    : ($step > 1 ? $max : $start);
            }

            if ($step < 1 || $start < $min || $end > $max || $start > $end) {
                throw new \InvalidArgumentException(
                    "Invalid cron expression '{$expression}': '{$part}' is outside {$min}-{$max}"
                );
            }

            for ($value = $start; $value <= $end; $value += $step) {
                $values[$value] = true;
            }
        }

        return $values;
    }

    /**
     * @param array<string, int> $names
     */
    private static function fieldValue(string $value, array $names, string $expression): int
    {
        if (ctype_digit($value)) {
            return (int)$value;
        }
        if (isset($names[$value])) {
            return $names[$value];
        }

        throw new \InvalidArgumentException("Invalid cron expression '{$expression}': unknown value '{$value}'");
    }

    /**
     * Load schedules from file
     */
    private function loadSchedules(): void
    {
        if (!file_exists($this->scheduleFile)) {
            return;
        }

        $content = file_get_contents($this->scheduleFile);
        if ($content === false) {
            throw new \RuntimeException("Cannot read schedule file {$this->scheduleFile}");
        }
        if (trim($content) === '') {
            return;
        }

        $schedules = json_decode($content, true);
        if (!is_array($schedules)) {
            // Refuse to continue: saving would overwrite the user's file
            throw new \RuntimeException("Schedule file {$this->scheduleFile} is not valid JSON");
        }

        $this->schedules = $schedules;
    }

    /**
     * Save schedules to file (atomically, via a temporary file)
     */
    private function saveSchedules(): void
    {
        $this->ensureDirectory();

        $json = json_encode($this->schedules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode schedules: ' . json_last_error_msg());
        }

        $tmp = $this->scheduleFile . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $this->scheduleFile)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write schedule file {$this->scheduleFile}");
        }
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->scheduleFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }
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
