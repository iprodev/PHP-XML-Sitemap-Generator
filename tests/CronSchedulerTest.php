<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\Scheduler\CronScheduler;
use PHPUnit\Framework\TestCase;

class CronSchedulerTest extends TestCase
{
    public function testBuildArgumentsMapsConfigKeysToCliOptions(): void
    {
        $args = CronScheduler::buildArguments([
            'url' => 'https://example.com',
            'maxPages' => 100,
            'max_depth' => 3,
            'public-base' => 'https://example.com',
            'enableJavaScript' => true,
            'verbose' => false,
            'exclude' => ['/admin/*', '/test/*'],
            'priorityRules' => ['/blog/*' => 0.8],
            'schedule' => 'daily',
        ]);

        $this->assertSame([
            '--url=https://example.com',
            '--max-pages=100',
            '--max-depth=3',
            '--public-base=https://example.com',
            '--enable-javascript',
            '--exclude=/admin/*,/test/*',
            '--priority-rules=' . json_encode(['/blog/*' => 0.8]),
        ], $args);
    }

    public function testShellMetacharactersStayLiteralArguments(): void
    {
        $args = CronScheduler::buildArguments([
            'url' => 'https://example.com/$(id)',
            'exclude' => ['/a', '/b; touch /tmp/pwned'],
        ]);

        $this->assertSame([
            '--url=https://example.com/$(id)',
            '--exclude=/a,/b; touch /tmp/pwned',
        ], $args);
    }

    public function testUnknownOrInvalidKeysAreSkipped(): void
    {
        $skipped = [];
        $args = CronScheduler::buildArguments([
            'x $(touch /tmp/pwned)' => '1',
            'help' => true,
            'interactive' => true,
            'out' => ['nested' => ['not', 'scalar']],
            'proxy' => "http://proxy\0:8080",
            'concurrency' => 5,
        ], $skipped);

        $this->assertSame(['--concurrency=5'], $args);
        $this->assertSame(['x $(touch /tmp/pwned)', 'help', 'interactive', 'out', 'proxy'], $skipped);
    }

    public function testArgumentsRunWithoutShell(): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is not available');
        }

        $marker = sys_get_temp_dir() . '/cron_scheduler_injection_' . uniqid();
        $args = CronScheduler::buildArguments(['url' => 'https://example.com/$(touch ' . $marker . ')']);

        $process = proc_open(
            array_merge([PHP_BINARY, '-r', 'echo $argv[1];', '--'], $args),
            [1 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        $this->assertSame('--url=https://example.com/$(touch ' . $marker . ')', $output);
        $this->assertFileDoesNotExist($marker);
    }

    /**
     * @dataProvider nextRunProvider
     */
    public function testNextRunTime(string $expression, string $expected): void
    {
        $after = new \DateTimeImmutable('2026-11-20 10:07:30'); // a Friday

        $this->assertSame($expected, CronScheduler::nextRunTime($expression, $after)->format('Y-m-d H:i'));
    }

    public static function nextRunProvider(): array
    {
        return [
            'every 5 minutes' => ['*/5 * * * *', '2026-11-20 10:10'],
            'daily at 2:00' => ['0 2 * * *', '2026-11-21 02:00'],
            'weekdays at 9:30' => ['30 9 * * 1-5', '2026-11-23 09:30'],
            'first of month' => ['0 0 1 * *', '2026-12-01 00:00'],
            'sunday as 7' => ['0 12 * * 7', '2026-11-22 12:00'],
            'day of month or weekday' => ['0 0 13 * fri', '2026-11-27 00:00'],
            'month names and lists' => ['15 14 1 jan,jul *', '2027-01-01 14:15'],
            'leap day' => ['0 0 29 2 *', '2028-02-29 00:00'],
            'range with step' => ['0 8-18/4 * * *', '2026-11-20 12:00'],
            'hourly alias' => ['hourly', '2026-11-20 11:00'],
            'daily alias' => ['daily', '2026-11-21 00:00'],
            'weekly alias is monday' => ['weekly', '2026-11-23 00:00'],
            '@weekly is sunday' => ['@weekly', '2026-11-22 00:00'],
            'monthly alias' => ['monthly', '2026-12-01 00:00'],
        ];
    }

    /**
     * @dataProvider invalidExpressionProvider
     */
    public function testInvalidExpressionsAreRejected(string $expression): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CronScheduler::nextRunTime($expression, new \DateTimeImmutable());
    }

    public static function invalidExpressionProvider(): array
    {
        return [
            'too few fields' => ['* * *'],
            'minute out of range' => ['61 * * * *'],
            'bad range' => ['0 5-2 * * *'],
            'zero step' => ['*/0 * * * *'],
            'unknown name' => ['0 0 * * funday'],
            'never matches' => ['0 0 31 2 *'],
        ];
    }

    public function testScheduleLifecycle(): void
    {
        $file = sys_get_temp_dir() . '/schedules_' . uniqid() . '.json';

        try {
            $scheduler = new CronScheduler($file);
            $scheduler->addSchedule('nightly', ['url' => 'https://example.com', 'schedule' => '0 3 * * *']);

            // Pretend it was due an hour ago
            $data = json_decode((string)file_get_contents($file), true);
            $data['nightly']['next_run'] = date('Y-m-d H:i:s', time() - 3600);
            file_put_contents($file, json_encode($data));

            $scheduler = new CronScheduler($file);
            $this->assertCount(1, $scheduler->getDueSchedules());

            // Marking as run before the crawl moves next_run into the future
            $scheduler->markAsRun('nightly');
            $scheduler->recordResult('nightly', 3);

            $reloaded = new CronScheduler($file);
            $this->assertCount(0, $reloaded->getDueSchedules());
            $schedule = $reloaded->getSchedules()['nightly'];
            $this->assertGreaterThan(time(), strtotime($schedule['next_run']));
            $this->assertSame('03:00:00', substr($schedule['next_run'], 11));
            $this->assertSame(3, $schedule['last_exit_code']);
        } finally {
            @unlink($file);
        }
    }

    public function testAddScheduleRejectsInvalidExpression(): void
    {
        $file = sys_get_temp_dir() . '/schedules_' . uniqid() . '.json';

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new CronScheduler($file))->addSchedule('bad', ['schedule' => 'every tuesday']);
        } finally {
            $this->assertFileDoesNotExist($file);
        }
    }

    public function testOnlyOneSchedulerCanHoldTheLock(): void
    {
        $file = sys_get_temp_dir() . '/schedules_' . uniqid() . '.json';

        try {
            $first = new CronScheduler($file);
            $second = new CronScheduler($file);

            $this->assertTrue($first->lock());
            $this->assertFalse($second->lock());

            $first->unlock();
            $this->assertTrue($second->lock());
            $second->unlock();
        } finally {
            @unlink($file . '.lock');
        }
    }

    public function testCorruptScheduleFileIsNotOverwritten(): void
    {
        $file = sys_get_temp_dir() . '/schedules_' . uniqid() . '.json';
        file_put_contents($file, '{"nightly": {broken');

        try {
            $this->expectException(\RuntimeException::class);
            new CronScheduler($file);
        } finally {
            $this->assertSame('{"nightly": {broken', file_get_contents($file));
            unlink($file);
        }
    }
}
