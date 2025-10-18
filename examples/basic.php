<?php
/**
 * Basic Sitemap Generation Example
 * 
 * This example demonstrates the simplest way to generate a sitemap
 */

require __DIR__ . '/../vendor/autoload.php';

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;

// Configuration
$startUrl = 'https://www.example.com';
$outputDir = __DIR__ . '/output';
$maxPages = 1000;
$maxDepth = 3;
$concurrency = 10;

try {
    echo "Starting sitemap generation for: {$startUrl}\n";
    echo str_repeat('-', 50) . "\n";
    
    // Initialize fetcher with concurrency
    $fetcher = new Fetcher(['concurrency' => $concurrency]);
    
    // Load and parse robots.txt
    echo "Loading robots.txt...\n";
    $robots = RobotsTxt::fromUrl($startUrl, $fetcher);
    echo "  Disallow rules: " . count($robots->getDisallows()) . "\n";
    echo "  Allow rules: " . count($robots->getAllows()) . "\n\n";
    
    // Create crawler
    $crawler = new Crawler($fetcher, $robots);
    
    // Start crawling
    echo "Starting crawl...\n";
    $startTime = microtime(true);
    
    $pages = $crawler->crawl($startUrl, $maxPages, $maxDepth);
    
    $crawlTime = microtime(true) - $startTime;
    echo "Crawl completed in " . round($crawlTime, 2) . " seconds\n";
    echo "Found " . count($pages) . " pages\n\n";
    
    // Write sitemap files
    echo "Writing sitemap files to: {$outputDir}\n";
    $files = SitemapWriter::write($pages, $outputDir, 50000, $startUrl);
    
    echo "\nSuccess! Generated files:\n";
    foreach ($files as $file) {
        $size = filesize($file);
        $sizeKB = round($size / 1024, 2);
        echo "  - " . basename($file) . " ({$sizeKB} KB)\n";
    }
    
    // Display statistics
    $stats = $crawler->getStats();
    echo "\nStatistics:\n";
    echo "  Processed: {$stats['processed']}\n";
    echo "  Unique URLs: {$stats['unique_urls']}\n";
    echo "  Average speed: " . round(count($pages) / $crawlTime, 2) . " pages/sec\n";
    
} catch (\InvalidArgumentException $e) {
    echo "Configuration Error: {$e->getMessage()}\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "Runtime Error: {$e->getMessage()}\n";
    exit(1);
} catch (\Throwable $e) {
    echo "Unexpected Error: {$e->getMessage()}\n";
    exit(1);
}
