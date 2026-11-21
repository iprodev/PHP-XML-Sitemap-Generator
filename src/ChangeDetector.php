<?php

namespace IProDev\Sitemap;

use IProDev\Sitemap\Database\Database;

class ChangeDetector
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Compare two crawls and detect changes
     */
    public function detectChanges(int $oldCrawlId, int $newCrawlId): array
    {
        $new = $this->db->getNewUrls($oldCrawlId, $newCrawlId);
        $modified = $this->db->getChangedUrls($oldCrawlId, $newCrawlId);
        $deleted = $this->db->getDeletedUrls($oldCrawlId, $newCrawlId);

        return [
            'new' => $new,
            'modified' => $modified,
            'deleted' => $deleted,
            'summary' => [
                'new_count' => count($new),
                'modified_count' => count($modified),
                'deleted_count' => count($deleted),
                'total_changes' => count($new) + count($modified) + count($deleted)
            ]
        ];
    }

    /**
     * Generate change report
     */
    public function generateReport(int $oldCrawlId, int $newCrawlId, string $format = 'text'): string
    {
        $changes = $this->detectChanges($oldCrawlId, $newCrawlId);

        switch ($format) {
            case 'json':
                return json_encode($changes, JSON_PRETTY_PRINT);

            case 'html':
                return $this->generateHtmlReport($changes);

            default:
                return $this->generateTextReport($changes);
        }
    }

    /**
     * Generate text report
     */
    private function generateTextReport(array $changes): string
    {
        $report = "SITEMAP CHANGE DETECTION REPORT\n";
        $report .= str_repeat('=', 70) . "\n\n";

        $report .= "SUMMARY\n";
        $report .= str_repeat('-', 70) . "\n";
        $report .= sprintf("New URLs:      %d\n", $changes['summary']['new_count']);
        $report .= sprintf("Modified URLs: %d\n", $changes['summary']['modified_count']);
        $report .= sprintf("Deleted URLs:  %d\n", $changes['summary']['deleted_count']);
        $report .= sprintf("Total Changes: %d\n\n", $changes['summary']['total_changes']);

        if (!empty($changes['new'])) {
            $report .= "NEW URLs\n";
            $report .= str_repeat('-', 70) . "\n";
            foreach ($changes['new'] as $url) {
                $report .= "  + {$url['url']}\n";
            }
            $report .= "\n";
        }

        if (!empty($changes['modified'])) {
            $report .= "MODIFIED URLs\n";
            $report .= str_repeat('-', 70) . "\n";
            foreach ($changes['modified'] as $url) {
                $report .= "  ~ {$url['url']}\n";
            }
            $report .= "\n";
        }

        if (!empty($changes['deleted'])) {
            $report .= "DELETED URLs\n";
            $report .= str_repeat('-', 70) . "\n";
            foreach ($changes['deleted'] as $url) {
                $report .= "  - {$url['url']}\n";
            }
            $report .= "\n";
        }

        return $report;
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport(array $changes): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Sitemap Change Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .summary { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .changes { margin-top: 20px; }
        .change-section { margin-bottom: 30px; }
        .change-section h2 { color: #555; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .url-list { list-style: none; padding: 0; }
        .url-list li { padding: 5px; margin: 2px 0; }
        .new { background: #d4edda; border-left: 3px solid #28a745; }
        .modified { background: #fff3cd; border-left: 3px solid #ffc107; }
        .deleted { background: #f8d7da; border-left: 3px solid #dc3545; }
    </style>
</head>
<body>
    <h1>Sitemap Change Detection Report</h1>
    
    <div class="summary">
        <h2>Summary</h2>
        <p><strong>New URLs:</strong> {$changes['summary']['new_count']}</p>
        <p><strong>Modified URLs:</strong> {$changes['summary']['modified_count']}</p>
        <p><strong>Deleted URLs:</strong> {$changes['summary']['deleted_count']}</p>
        <p><strong>Total Changes:</strong> {$changes['summary']['total_changes']}</p>
    </div>
    
    <div class="changes">
HTML;

        if (!empty($changes['new'])) {
            $html .= '<div class="change-section"><h2>New URLs</h2><ul class="url-list">';
            foreach ($changes['new'] as $url) {
                $html .= "<li class='new'>" . self::escapeHtml((string)$url['url']) . '</li>';
            }
            $html .= '</ul></div>';
        }

        if (!empty($changes['modified'])) {
            $html .= '<div class="change-section"><h2>Modified URLs</h2><ul class="url-list">';
            foreach ($changes['modified'] as $url) {
                $html .= "<li class='modified'>" . self::escapeHtml((string)$url['url']) . '</li>';
            }
            $html .= '</ul></div>';
        }

        if (!empty($changes['deleted'])) {
            $html .= '<div class="change-section"><h2>Deleted URLs</h2><ul class="url-list">';
            foreach ($changes['deleted'] as $url) {
                $html .= "<li class='deleted'>" . self::escapeHtml((string)$url['url']) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Escape text for HTML output. URLs come from the crawled site and must be
     * treated as untrusted.
     */
    private static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Calculate content hash
     */
    public static function calculateHash(string $content): string
    {
        // Remove dynamic content
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        return hash('sha256', trim($content));
    }
}
