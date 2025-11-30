<?php

namespace IProDev\Sitemap;

/**
 * JavaScript-enabled fetcher using headless browser
 */
class JavaScriptFetcher extends Fetcher
{
    private ?HeadlessBrowser $browser = null;
    private bool $enableJavaScript;

    public function __construct(array $options = [], ?\Psr\Log\LoggerInterface $logger = null)
    {
        parent::__construct($options, $logger);

        $this->enableJavaScript = $options['enable_javascript'] ?? false;

        if ($this->enableJavaScript) {
            $this->browser = new HeadlessBrowser(
                $options['chrome_path'] ?? '/usr/bin/chromium',
                $options['chrome_port'] ?? 9222,
                $options['wait_for_ajax'] ?? true,
                $options['ajax_timeout'] ?? 5000,
                $logger
            );
        }
    }

    /**
     * Fetch URL with optional JavaScript rendering
     */
    public function get(string $url)
    {
        // Check if URL needs JavaScript rendering
        if ($this->shouldRenderJavaScript($url)) {
            return $this->fetchWithJavaScript($url);
        }

        // Use regular fetch
        return parent::get($url);
    }

    /**
     * Fetch with JavaScript rendering
     */
    private function fetchWithJavaScript(string $url)
    {
        if (!$this->browser) {
            throw new \RuntimeException("JavaScript rendering not enabled");
        }

        $result = $this->browser->render($url);

        // Create mock PSR-7 response
        return new \GuzzleHttp\Psr7\Response(
            200,
            ['Content-Type' => 'text/html'],
            $result['html']
        );
    }

    /**
     * Check if URL should be rendered with JavaScript
     */
    private function shouldRenderJavaScript(string $url): bool
    {
        if (!$this->enableJavaScript) {
            return false;
        }

        // Add patterns that typically need JS rendering
        $jsPatterns = [
            '#/app/#',
            '#/react/#',
            '#/angular/#',
            '#/vue/#',
        ];

        foreach ($jsPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    public function __destruct()
    {
        if ($this->browser) {
            $this->browser->stop();
        }
    }
}
