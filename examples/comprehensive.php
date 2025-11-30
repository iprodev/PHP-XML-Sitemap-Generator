<?php
/**
 * Comprehensive Example - Using All Features
 * 
 * This example demonstrates how to use all advanced features
 */

require __DIR__ . '/../vendor/autoload.php';

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Crawler;
use IProDev\Sitemap\SitemapWriter;
use IProDev\Sitemap\RobotsTxt;
use IProDev\Sitemap\Utils;
use IProDev\Sitemap\Cache\FileCache;
use IProDev\Sitemap\Database\Database;
use IProDev\Sitemap\ChangeDetector;
use IProDev\Sitemap\RateLimiter;
use IProDev\Sitemap\Filter\UrlFilter;
use IProDev\Sitemap\Analyzer\SeoAnalyzer;
use IProDev\Sitemap\Analyzer\ContentQualityChecker;
use IProDev\Sitemap\PerformanceMetrics;
use IProDev\Sitemap\WebhookNotifier;
use IProDev\Sitemap\CrawlCheckpoint;
use IProDev\Sitemap\Sitemap\ImageSitemapWriter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Configuration
$config = [
    'url' => 'https://www.example.com',
    'output_dir' => __DIR__ . '/output',
    'concurrency' => 20,
    'max_pages' => 10000,
    'max_depth' => 5,
    'public_base' => 'https://www.example.com',
    
    // Advanced features
    'cache_enabled' => true,
    'db_enabled' => true,
    'detect_changes' => true,
    'rate_limit' => 100, // requests per minute
    'seo_analysis' => true,
    'resume_enabled' => true,
    'webhook_url' => 'https://example.com/webhook'
];

try {
    // Setup logger
    $logger = new Logger('sitemap');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    $logger->pushHandler(new StreamHandler('./sitemap.log', Logger::DEBUG));
    
    $logger->info('Starting comprehensive sitemap generation');
    
    $startTime = microtime(true);
    
    // 1. Setup Cache
    $cache = new FileCache('./cache', 3600);
    $logger->info('Cache initialized');
    
    // 2. Setup Database
    $db = new Database('sqlite:./sitemap.db');
    $db->createTables();
    $domain = Utils::getDomain($config['url']);
    $crawlId = $db->startCrawl($domain, $config['url'], $config);
    $logger->info("Database initialized", ['crawl_id' => $crawlId]);
    
    // 3. Setup Rate Limiter
    $rateLimiter = new RateLimiter($config['rate_limit'], 60);
    $logger->info('Rate limiter configured');
    
    // 4. Setup URL Filter
    $filter = new UrlFilter([
        'exclude' => ['/admin/*', '/test/*', '*.pdf'],
        'include' => ['/products/*', '/blog/*', '/services/*'],
        'priority' => [
            'homepage' => 1.0,
            '/products/*' => 0.8,
            '/blog/*' => 0.6,
            '/services/*' => 0.7
        ]
    ]);
    $logger->info('URL filter configured');
    
    // 5. Setup Webhooks
    $webhooks = new WebhookNotifier([], $logger);
    if (!empty($config['webhook_url'])) {
        $webhooks->addWebhook($config['webhook_url'], ['*']);
        $logger->info('Webhooks configured');
    }
    
    // 6. Setup Performance Metrics
    $metrics = new PerformanceMetrics();
    $logger->info('Performance tracking enabled');
    
    // 7. Setup Checkpoint for Resume
    $checkpoint = new CrawlCheckpoint('./checkpoint.json', 1000);
    if ($checkpoint->exists()) {
        $info = $checkpoint->getInfo();
        $logger->info('Found existing checkpoint', $info);
    }
    
    // Notify start
    $webhooks->notifyCrawlStarted($config['url'], $config);
    
    // 8. Initialize Fetcher
    $fetcher = new Fetcher([
        'concurrency' => $config['concurrency'],
        'timeout' => 15
    ], $logger);
    
    // 9. Load Robots.txt
    $logger->info('Loading robots.txt');
    $robots = RobotsTxt::fromUrl($config['url'], $fetcher);
    $logger->info('Robots.txt loaded', [
        'disallows' => count($robots->getDisallows()),
        'allows' => count($robots->getAllows())
    ]);
    
    // 10. Create Crawler
    $crawler = new Crawler($fetcher, $robots, $logger);
    
    // 11. Start Crawling
    $logger->info('Starting crawl');
    $pages = $crawler->crawl($config['url'], $config['max_pages'], $config['max_depth']);
    
    // 12. Apply Filters
    $logger->info('Applying filters');
    $pages = $filter->filterPages($pages);
    
    $crawlTime = microtime(true) - $startTime;
    $logger->info('Crawl completed', [
        'pages' => count($pages),
        'duration' => Utils::formatDuration($crawlTime)
    ]);
    
    // 13. SEO Analysis
    if ($config['seo_analysis']) {
        $logger->info('Running SEO analysis');
        $seoAnalyzer = new SeoAnalyzer();
        $seoResults = [];
        
        foreach ($pages as &$page) {
            // Simulate having HTML (in real scenario, would fetch again)
            $html = $page['html'] ?? '<html><body>Sample</body></html>';
            
            $analysis = $seoAnalyzer->analyze(
                $page['url'],
                $html,
                $page['status'] ?? 200
            );
            
            $page['seo_score'] = $analysis['score'];
            $seoResults[] = $analysis;
        }
        
        $avgScore = array_sum(array_column($seoResults, 'score')) / count($seoResults);
        $logger->info("SEO analysis complete", ['average_score' => round($avgScore, 2)]);
    }
    
    // 14. Content Quality Check
    $logger->info('Running quality checks');
    $qualityChecker = new ContentQualityChecker();
    
    $duplicates = $qualityChecker->findDuplicates($pages);
    $brokenLinks = $qualityChecker->findBrokenLinks($pages);
    $thinContent = $qualityChecker->findThinContent($pages, 300);
    
    $logger->warning('Quality issues found', [
        'duplicates' => count($duplicates),
        'broken_links' => count($brokenLinks),
        'thin_content' => count($thinContent)
    ]);
    
    // 15. Save to Database
    $logger->info('Saving to database');
    foreach ($pages as $page) {
        // Add content hash
        if (isset($page['html'])) {
            $page['content_hash'] = ChangeDetector::calculateHash($page['html']);
        }
        
        $db->saveUrl($crawlId, $page);
    }
    
    // 16. Detect Changes
    if ($config['detect_changes']) {
        $logger->info('Detecting changes');
        $prevCrawl = $db->getPreviousCrawl($domain, $crawlId);
        
        if ($prevCrawl) {
            $detector = new ChangeDetector($db);
            $changes = $detector->detectChanges($prevCrawl['id'], $crawlId);
            
            $logger->info('Changes detected', $changes['summary']);
            
            // Generate change report
            $reportHtml = $detector->generateReport($prevCrawl['id'], $crawlId, 'html');
            file_put_contents($config['output_dir'] . '/change-report.html', $reportHtml);
            
            // Notify about changes
            $webhooks->notifyChangesDetected($changes);
        } else {
            $logger->info('No previous crawl found for comparison');
        }
    }
    
    // 17. Complete Database Crawl Record
    $stats = $crawler->getStats();
    $db->completeCrawl($crawlId, [
        'total_pages' => count($pages),
        'new_pages' => 0,
        'modified_pages' => 0,
        'deleted_pages' => 0,
        'errors' => 0
    ]);
    
    // 18. Write Standard Sitemap
    $logger->info('Writing sitemap files');
    $files = SitemapWriter::write($pages, $config['output_dir'], 50000, $config['public_base']);
    
    // 19. Write Image Sitemap
    $logger->info('Generating image sitemap');
    $pagesWithImages = [];
    foreach ($pages as $page) {
        // Simulate extracting images
        $page['images'] = [
            [
                'url' => $page['url'] . '/image1.jpg',
                'title' => 'Sample Image',
                'caption' => 'Image caption'
            ]
        ];
        $pagesWithImages[] = $page;
    }
    $imageFiles = ImageSitemapWriter::write($pagesWithImages, $config['output_dir']);
    $files = array_merge($files, $imageFiles);
    
    // 20. Generate Performance Report
    $logger->info('Generating performance report');
    $perfReport = $metrics->generateReport('text');
    file_put_contents($config['output_dir'] . '/performance.txt', $perfReport);
    
    // 21. Export Metrics to CSV
    $metrics->exportToCsv($config['output_dir'] . '/metrics.csv');
    
    // 22. Clean up checkpoint
    if ($checkpoint->exists()) {
        $checkpoint->delete();
        $logger->info('Checkpoint cleaned up');
    }
    
    $totalTime = microtime(true) - $startTime;
    
    // 23. Display Summary
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  ✅ Comprehensive Sitemap Generation Complete!\n";
    echo str_repeat('=', 70) . "\n\n";
    
    echo "Generated Files:\n";
    foreach ($files as $file) {
        $size = file_exists($file) ? Utils::formatBytes(filesize($file)) : 'N/A';
        echo "  • " . basename($file) . " ({$size})\n";
    }
    
    echo "\nStatistics:\n";
    echo "  • Total Pages:       " . count($pages) . "\n";
    echo "  • Unique URLs:       " . $stats['unique_urls'] . "\n";
    echo "  • Processed:         " . $stats['processed'] . "\n";
    echo "  • Total Time:        " . Utils::formatDuration($totalTime) . "\n";
    echo "  • Average Score:     " . (isset($avgScore) ? round($avgScore, 2) : 'N/A') . "/100\n";
    echo "  • Memory Peak:       " . Utils::getPeakMemoryUsage() . "\n";
    
    echo "\nQuality Issues:\n";
    echo "  • Duplicates:        " . count($duplicates) . "\n";
    echo "  • Broken Links:      " . count($brokenLinks) . "\n";
    echo "  • Thin Content:      " . count($thinContent) . "\n";
    
    echo "\nReports Generated:\n";
    echo "  • Change Report:     change-report.html\n";
    echo "  • Performance:       performance.txt\n";
    echo "  • Metrics CSV:       metrics.csv\n";
    
    echo "\nOutput Directory:    " . $config['output_dir'] . "\n";
    echo str_repeat('=', 70) . "\n";
    
    // 24. Notify completion
    $webhooks->notifySitemapGenerated($files, [
        'pages' => count($pages),
        'duration' => $totalTime,
        'seo_score' => $avgScore ?? 0
    ]);
    
    $logger->info('All tasks completed successfully');
    
} catch (\Throwable $e) {
    $logger->error('Fatal error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    if (isset($webhooks)) {
        $webhooks->notifyCrawlFailed($config['url'], $e->getMessage());
    }
    
    echo "\n❌ Error: {$e->getMessage()}\n";
    exit(1);
}
