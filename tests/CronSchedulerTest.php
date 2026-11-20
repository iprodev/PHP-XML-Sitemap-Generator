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
}
