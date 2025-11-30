<?php

namespace IProDev\Sitemap;

class PerformanceMetrics
{
    private array $metrics = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->metrics = [
            'requests' => [],
            'errors' => [],
            'timings' => [],
            'memory' => []
        ];
    }

    /**
     * Record HTTP request
     */
    public function recordRequest(string $url, int $statusCode, float $duration, int $size = 0): void
    {
        $this->metrics['requests'][] = [
            'url' => $url,
            'status_code' => $statusCode,
            'duration' => $duration,
            'size' => $size,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Record error
     */
    public function recordError(string $url, string $error, string $type = 'general'): void
    {
        $this->metrics['errors'][] = [
            'url' => $url,
            'error' => $error,
            'type' => $type,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Record timing
     */
    public function recordTiming(string $operation, float $duration): void
    {
        $this->metrics['timings'][$operation] = $duration;
    }

    /**
     * Record memory usage
     */
    public function recordMemory(string $label = 'checkpoint'): void
    {
        $this->metrics['memory'][] = [
            'label' => $label,
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Get comprehensive statistics
     */
    public function getStats(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        $requests = $this->metrics['requests'];
        $errors = $this->metrics['errors'];

        return [
            'summary' => [
                'total_time' => round($totalTime, 2),
                'total_requests' => count($requests),
                'total_errors' => count($errors),
                'requests_per_second' => count($requests) > 0 ? round(count($requests) / $totalTime, 2) : 0,
                'error_rate' => count($requests) > 0 ? round((count($errors) / count($requests)) * 100, 2) : 0,
                'peak_memory' => memory_get_peak_usage(true),
                'current_memory' => memory_get_usage(true)
            ],
            'response_times' => $this->getResponseTimeStats($requests),
            'status_codes' => $this->getStatusCodeDistribution($requests),
            'content_sizes' => $this->getContentSizeStats($requests),
            'errors_by_type' => $this->getErrorsByType($errors),
            'timings' => $this->metrics['timings'],
            'memory_timeline' => $this->metrics['memory']
        ];
    }

    /**
     * Get response time statistics
     */
    private function getResponseTimeStats(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $times = array_column($requests, 'duration');
        sort($times);

        $count = count($times);
        return [
            'min' => round(min($times), 3),
            'max' => round(max($times), 3),
            'avg' => round(array_sum($times) / $count, 3),
            'median' => round($times[(int)($count / 2)], 3),
            'p95' => round($times[(int)($count * 0.95)], 3),
            'p99' => round($times[(int)($count * 0.99)], 3)
        ];
    }

    /**
     * Get status code distribution
     */
    private function getStatusCodeDistribution(array $requests): array
    {
        $distribution = [];

        foreach ($requests as $request) {
            $code = $request['status_code'];
            $category = $this->getStatusCodeCategory($code);

            if (!isset($distribution[$category])) {
                $distribution[$category] = 0;
            }
            $distribution[$category]++;
        }

        return $distribution;
    }

    /**
     * Get content size statistics
     */
    private function getContentSizeStats(array $requests): array
    {
        $sizes = array_filter(array_column($requests, 'size'));

        if (empty($sizes)) {
            return [];
        }

        sort($sizes);
        $count = count($sizes);

        return [
            'total' => array_sum($sizes),
            'min' => min($sizes),
            'max' => max($sizes),
            'avg' => (int)(array_sum($sizes) / $count),
            'median' => $sizes[(int)($count / 2)]
        ];
    }

    /**
     * Get errors by type
     */
    private function getErrorsByType(array $errors): array
    {
        $byType = [];

        foreach ($errors as $error) {
            $type = $error['type'];
            if (!isset($byType[$type])) {
                $byType[$type] = 0;
            }
            $byType[$type]++;
        }

        return $byType;
    }

    /**
     * Get status code category
     */
    private function getStatusCodeCategory(int $code): string
    {
        if ($code >= 200 && $code < 300) {
            return '2xx_success';
        } elseif ($code >= 300 && $code < 400) {
            return '3xx_redirect';
        } elseif ($code >= 400 && $code < 500) {
            return '4xx_client_error';
        } elseif ($code >= 500) {
            return '5xx_server_error';
        }
        return 'other';
    }

    /**
     * Generate performance report
     */
    public function generateReport(string $format = 'text'): string
    {
        $stats = $this->getStats();

        if ($format === 'json') {
            return json_encode($stats, JSON_PRETTY_PRINT);
        }

        return $this->generateTextReport($stats);
    }

    /**
     * Generate text report
     */
    private function generateTextReport(array $stats): string
    {
        $report = "PERFORMANCE REPORT\n";
        $report .= str_repeat('=', 70) . "\n\n";

        // Summary
        $report .= "SUMMARY\n";
        $report .= str_repeat('-', 70) . "\n";
        foreach ($stats['summary'] as $key => $value) {
            $label = str_replace('_', ' ', ucfirst($key));
            if (is_numeric($value)) {
                if ($key === 'peak_memory' || $key === 'current_memory') {
                    $value = Utils::formatBytes($value);
                }
            }
            $report .= sprintf("%-25s: %s\n", $label, $value);
        }
        $report .= "\n";

        // Response Times
        if (!empty($stats['response_times'])) {
            $report .= "RESPONSE TIMES (seconds)\n";
            $report .= str_repeat('-', 70) . "\n";
            foreach ($stats['response_times'] as $key => $value) {
                $report .= sprintf("%-25s: %.3f\n", strtoupper($key), $value);
            }
            $report .= "\n";
        }

        // Status Codes
        if (!empty($stats['status_codes'])) {
            $report .= "STATUS CODE DISTRIBUTION\n";
            $report .= str_repeat('-', 70) . "\n";
            foreach ($stats['status_codes'] as $category => $count) {
                $report .= sprintf("%-25s: %d\n", $category, $count);
            }
            $report .= "\n";
        }

        return $report;
    }

    /**
     * Export metrics to CSV
     */
    public function exportToCsv(string $filename): void
    {
        $fp = fopen($filename, 'w');

        // Headers
        fputcsv($fp, ['URL', 'Status Code', 'Duration (s)', 'Size (bytes)', 'Timestamp']);

        // Data
        foreach ($this->metrics['requests'] as $request) {
            fputcsv($fp, [
                $request['url'],
                $request['status_code'],
                round($request['duration'], 3),
                $request['size'],
                date('Y-m-d H:i:s', (int)$request['timestamp'])
            ]);
        }

        fclose($fp);
    }
}
