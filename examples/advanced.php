<?php
/**
 * Advanced Sitemap Generation with Logging
 * 
 * This example demonstrates using the library with Monolog for logging
 * Requires: composer require monolog/monolog
 */

require __DIR__ . '/../vendor/autoload.php';

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;
use IProDev\Sitemap\Utils;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

// Configuration
$config = [
    'url' => 'https://www.example.com',
    'output_dir' => __DIR__ . '/output',
    'log_dir' => __DIR__ . '/logs',
    'concurrency' => 20,
    'max_pages' => 10000,
    'max_depth' => 5,
    'public_base' => 'https://www.example.com',
];

try {
    // Ensure directories exist
    Utils::ensureDirectory($config['output_dir']);
    Utils::ensureDirectory($config['log_dir']);
    
    // Setup Monolog logger
    $logger = new Logger('sitemap');
    
    // Console handler with colors
    $consoleHandler = new StreamHandler('php://stdout', Logger::DEBUG);
    $consoleFormatter = new LineFormatter(
        "[%datetime%] %level_name%: %message% %context%\n",
        "H:i:s"
    );
    $consoleHandler->setFormatter($consoleFormatter);
    $logger->pushHandler($consoleHandler);
    
    // File handler with rotation
    $fileHandler = new RotatingFileHandler(
        $config['log_dir'] . '/sitemap.log',
        30, // Keep 30 days
        Logger::INFO
    );
    $logger->pushHandler($fileHandler);
    
    // Start process
    $logger->info('Starting sitemap generation', [
        'url' => $config['url'],
        'max_pages' => $config['max_pages']
    ]);
    
    $startTime = microtime(true);
    
    // Initialize fetcher with custom options
    $fetcher = new Fetcher([
        'concurrency' => $config['concurrency'],
        'timeout' => 15,
        'connect_timeout' => 10,
        'headers' => [
            'User-Agent' => 'CustomBot/1.0 (+https://example.com/bot)',
        ]
    ], $logger);
    
    // Load robots.txt
    $logger->info('Loading robots.txt');
    $robots = RobotsTxt::fromUrl($config['url'], $fetcher);
    $logger->info('Robots.txt loaded', [
        'disallows' => count($robots->getDisallows()),
        'allows' => count($robots->getAllows())
    ]);
    
    // Create crawler with logger
    $crawler = new Crawler($fetcher, $robots, $logger);
    
    // Start crawling with progress updates
    $logger->info('Starting crawl');
    $pages = $crawler->crawl(
        $config['url'],
        $config['max_pages'],
        $config['max_depth']
    );
    
    $crawlTime = microtime(true) - $startTime;
    $logger->info('Crawl completed', [
        'pages' => count($pages),
        'duration' => Utils::formatDuration($crawlTime),
        'speed' => round(count($pages) / $crawlTime, 2) . ' pages/sec'
    ]);
    
    // Write sitemap files
    $logger->info('Writing sitemap files', [
        'output_dir' => $config['output_dir']
    ]);
    
    $files = SitemapWriter::write(
        $pages,
        $config['output_dir'],
        50000,
        $config['public_base']
    );
    
    $totalTime = microtime(true) - $startTime;
    
    // Log success with detailed statistics
    $logger->info('Sitemap generation completed successfully', [
        'total_time' => Utils::formatDuration($totalTime),
        'files_generated' => count($files),
        'memory_peak' => Utils::getPeakMemoryUsage()
    ]);
    
    // Display file information
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "Generated Files:\n";
    foreach ($files as $file) {
        if (file_exists($file)) {
            $size = Utils::formatBytes(filesize($file));
            echo "  • " . basename($file) . " ({$size})\n";
        }
    }
    
    // Display statistics
    $stats = $crawler->getStats();
    echo "\nStatistics:\n";
    echo "  • Processed Pages: {$stats['processed']}\n";
    echo "  • Unique URLs: {$stats['unique_urls']}\n";
    echo "  • Total Time: " . Utils::formatDuration($totalTime) . "\n";
    echo "  • Crawl Speed: " . round(count($pages) / $crawlTime, 2) . " pages/sec\n";
    echo "  • Memory Peak: " . Utils::getPeakMemoryUsage() . "\n";
    echo str_repeat('=', 70) . "\n";
    
} catch (\InvalidArgumentException $e) {
    if (isset($logger)) {
        $logger->error('Configuration error', ['message' => $e->getMessage()]);
    }
    echo "Configuration Error: {$e->getMessage()}\n";
    exit(1);
} catch (\RuntimeException $e) {
    if (isset($logger)) {
        $logger->error('Runtime error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    echo "Runtime Error: {$e->getMessage()}\n";
    exit(1);
} catch (\Throwable $e) {
    if (isset($logger)) {
        $logger->critical('Unexpected error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    echo "Unexpected Error: {$e->getMessage()}\n";
    exit(1);
}
