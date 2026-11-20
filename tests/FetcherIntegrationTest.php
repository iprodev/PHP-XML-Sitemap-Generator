<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Http\BlockedRequestException;
use IProDev\Sitemap\Http\ResponseTooLargeException;
use PHPUnit\Framework\TestCase;

/**
 * Runs the Fetcher with the real cURL handler against PHP's built-in web server.
 */
class FetcherIntegrationTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('proc_open') || !extension_loaded('curl')) {
            return;
        }

        $socket = @stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            return;
        }
        $port = (int)substr(strrchr((string)stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $router = __DIR__ . '/fixtures/http-server.php';
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", $router],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes
        );

        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($connection) {
                fclose($connection);
                self::$baseUrl = "http://127.0.0.1:{$port}";
                return;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
        self::$baseUrl = '';
    }

    protected function setUp(): void
    {
        if (self::$baseUrl === '') {
            $this->markTestSkipped('Could not start the PHP built-in web server');
        }
    }

    private function fetcher(array $options = []): Fetcher
    {
        // An empty proxy disables proxies from the environment
        return new Fetcher($options + ['max_response_size' => 1024 * 1024, 'proxy' => '']);
    }

    /**
     * @return array{responses: array, errors: array}
     */
    private function fetchAll(Fetcher $fetcher, array $urls): array
    {
        $responses = [];
        $errors = [];
        $fetcher->fetchMany(
            $urls,
            function ($response, $idx) use (&$responses) {
                $responses[$idx] = $response;
            },
            function ($reason, $idx) use (&$errors) {
                $errors[$idx] = $reason;
            }
        );

        return ['responses' => $responses, 'errors' => $errors];
    }

    public function testNormalResponsesAndRedirects(): void
    {
        $result = $this->fetchAll($this->fetcher(), [
            'ok' => self::$baseUrl . '/ok',
            'redirect' => self::$baseUrl . '/redirect',
        ]);

        $this->assertEmpty($result['errors']);
        $this->assertStringContainsString('other', (string)$result['responses']['ok']->getBody());
        $this->assertStringContainsString('other', (string)$result['responses']['redirect']->getBody());
        $this->assertSame(
            [self::$baseUrl . '/ok'],
            $result['responses']['redirect']->getHeader('X-Guzzle-Redirect-History')
        );
    }

    public function testOversizedResponsesAreRejected(): void
    {
        $result = $this->fetchAll($this->fetcher(), [
            'streamed' => self::$baseUrl . '/big-streamed',
            'announced' => self::$baseUrl . '/big-announced',
            'bomb' => self::$baseUrl . '/gzip-bomb',
            'ok' => self::$baseUrl . '/ok',
        ]);

        $this->assertSame(['ok'], array_keys($result['responses']));
        foreach (['streamed', 'announced', 'bomb'] as $key) {
            $this->assertInstanceOf(ResponseTooLargeException::class, $result['errors'][$key], $key);
        }
    }

    public function testSizeLimitCanBeDisabled(): void
    {
        $result = $this->fetchAll($this->fetcher(['max_response_size' => 0]), [self::$baseUrl . '/big-streamed']);

        $this->assertSame(2 * 1024 * 1024, strlen((string)$result['responses'][0]->getBody()));
    }

    public function testPrivateAddressesCanBeBlocked(): void
    {
        $result = $this->fetchAll($this->fetcher(['block_private_ips' => true]), [self::$baseUrl . '/ok']);

        $this->assertEmpty($result['responses']);
        $this->assertInstanceOf(BlockedRequestException::class, $result['errors'][0]);
    }
}
