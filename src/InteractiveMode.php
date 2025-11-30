<?php

namespace IProDev\Sitemap;

class InteractiveMode
{
    private array $config = [];

    /**
     * Start interactive configuration
     */
    public function run(): array
    {
        echo "\n";
        echo str_repeat('=', 70) . "\n";
        echo "  PHP XML Sitemap Generator - Interactive Mode\n";
        echo str_repeat('=', 70) . "\n\n";

        echo "This wizard will help you configure your sitemap generation.\n";
        echo "Press Enter to use default values shown in brackets.\n\n";

        // URL
        $this->config['url'] = $this->ask('Starting URL', null, true, function ($value) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }, 'Please enter a valid URL');

        // Output directory
        $this->config['out'] = $this->ask('Output directory', './output');

        // Concurrency
        $this->config['concurrency'] = (int)$this->ask(
            'Concurrent requests (1-100)',
            '10',
            false,
            function ($value) {
                return is_numeric($value) && $value >= 1 && $value <= 100;
            },
            'Please enter a number between 1 and 100'
        );

        // Max pages
        $this->config['maxPages'] = (int)$this->ask(
            'Maximum pages to crawl',
            '10000',
            false,
            function ($value) {
                return is_numeric($value) && $value > 0;
            },
            'Please enter a positive number'
        );

        // Max depth
        $this->config['maxDepth'] = (int)$this->ask(
            'Maximum link depth',
            '5',
            false,
            function ($value) {
                return is_numeric($value) && $value >= 0;
            },
            'Please enter a non-negative number'
        );

        // Public base
        if ($this->askYesNo('Do you want to specify a public base URL?', false)) {
            $this->config['publicBase'] = $this->ask('Public base URL', $this->config['url']);
        }

        // Advanced options
        if ($this->askYesNo('Configure advanced options?', false)) {
            $this->configureAdvanced();
        }

        // Features
        if ($this->askYesNo('Enable advanced features?', false)) {
            $this->configureFeatures();
        }

        // Summary
        $this->displaySummary();

        if (!$this->askYesNo('Start crawling with these settings?', true)) {
            echo "\nConfiguration cancelled.\n";
            exit(0);
        }

        return $this->config;
    }

    /**
     * Configure advanced options
     */
    private function configureAdvanced(): void
    {
        echo "\n--- Advanced Options ---\n\n";

        // Rate limiting
        if ($this->askYesNo('Enable rate limiting?', false)) {
            $this->config['rateLimit'] = (int)$this->ask('Requests per minute', '100');
            $this->config['delay'] = (int)$this->ask('Delay between requests (ms)', '0');
        }

        // Caching
        if ($this->askYesNo('Enable caching?', false)) {
            $this->config['cacheEnabled'] = true;
            $this->config['cacheTtl'] = (int)$this->ask('Cache TTL (seconds)', '3600');
        }

        // Resume
        if ($this->askYesNo('Enable resume capability?', false)) {
            $this->config['resume'] = true;
            $this->config['checkpointInterval'] = (int)$this->ask('Checkpoint interval (pages)', '1000');
        }
    }

    /**
     * Configure features
     */
    private function configureFeatures(): void
    {
        echo "\n--- Advanced Features ---\n\n";

        // Database storage
        if ($this->askYesNo('Enable database storage for change detection?', false)) {
            $this->config['dbEnabled'] = true;
            $this->config['dbDsn'] = $this->ask('Database DSN', 'sqlite:./sitemap.db');
        }

        // SEO Analysis
        if ($this->askYesNo('Enable SEO analysis?', false)) {
            $this->config['seoAnalysis'] = true;
        }

        // Image sitemap
        if ($this->askYesNo('Generate image sitemap?', false)) {
            $this->config['imageSitemap'] = true;
        }

        // Video sitemap
        if ($this->askYesNo('Generate video sitemap?', false)) {
            $this->config['videoSitemap'] = true;
        }

        // Webhooks
        if ($this->askYesNo('Configure webhook notifications?', false)) {
            $this->config['webhookUrl'] = $this->ask('Webhook URL');
            $this->config['notifyOnComplete'] = $this->askYesNo('Notify on completion?', true);
            $this->config['notifyOnError'] = $this->askYesNo('Notify on errors?', true);
        }

        // Filtering
        if ($this->askYesNo('Configure URL filters?', false)) {
            echo "Enter exclude patterns (comma-separated, e.g., /admin/*,/test/*): ";
            $excludeInput = trim(fgets(STDIN));
            if (!empty($excludeInput)) {
                $this->config['excludePatterns'] = array_map('trim', explode(',', $excludeInput));
            }
        }
    }

    /**
     * Display configuration summary
     */
    private function displaySummary(): void
    {
        echo "\n";
        echo str_repeat('=', 70) . "\n";
        echo "  Configuration Summary\n";
        echo str_repeat('=', 70) . "\n\n";

        foreach ($this->config as $key => $value) {
            if (is_array($value)) {
                echo sprintf("%-25s: %s\n", $key, implode(', ', $value));
            } elseif (is_bool($value)) {
                echo sprintf("%-25s: %s\n", $key, $value ? 'Yes' : 'No');
            } else {
                echo sprintf("%-25s: %s\n", $key, $value);
            }
        }
        echo "\n";
    }

    /**
     * Ask question with validation
     */
    private function ask(
        string $question,
        ?string $default = null,
        bool $required = false,
        ?callable $validator = null,
        ?string $errorMessage = null
    ): string {
        while (true) {
            if ($default !== null) {
                echo "{$question} [{$default}]: ";
            } else {
                echo "{$question}: ";
            }

            $answer = trim(fgets(STDIN));

            if (empty($answer) && $default !== null) {
                $answer = $default;
            }

            if ($required && empty($answer)) {
                echo "This field is required.\n";
                continue;
            }

            if ($validator && !$validator($answer)) {
                echo ($errorMessage ?? "Invalid input.") . "\n";
                continue;
            }

            return $answer;
        }
    }

    /**
     * Ask yes/no question
     */
    private function askYesNo(string $question, bool $default = false): bool
    {
        $defaultStr = $default ? 'Y/n' : 'y/N';
        echo "{$question} [{$defaultStr}]: ";

        $answer = strtolower(trim(fgets(STDIN)));

        if (empty($answer)) {
            return $default;
        }

        return in_array($answer, ['y', 'yes', '1', 'true']);
    }

    /**
     * Save configuration to file
     */
    public function saveConfig(string $filename = './sitemap.config.php'): void
    {
        $content = "<?php\n\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($filename, $content);
        echo "\nConfiguration saved to: {$filename}\n";
    }

    /**
     * Load configuration from file
     */
    public static function loadConfig(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("Configuration file not found: {$filename}");
        }

        return include $filename;
    }
}
