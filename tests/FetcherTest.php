<?php

namespace IProDev\Sitemap\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use IProDev\Sitemap\Fetcher;
use IProDev\Sitemap\Http\BlockedRequestException;
use IProDev\Sitemap\Http\PrivateNetworkGuard;
use IProDev\Sitemap\Http\ResponseTooLargeException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class FetcherTest extends TestCase
{
    public function testCallbacksReceiveTheOriginalIndexWhenInvalidUrlsAreSkipped(): void
    {
        $fetcher = new Fetcher(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], 'A'),
            new Response(200, [], 'B'),
        ]))]);

        $bodies = [];
        $fetcher->fetchMany(
            [0 => 'not a url', 1 => 'https://example.com/a', 2 => 'https://example.com/b'],
            function ($response, $idx) use (&$bodies) {
                $bodies[$idx] = (string)$response->getBody();
            }
        );

        $this->assertSame([1 => 'A', 2 => 'B'], $bodies);
    }

    public function testBodyLargerThanLimitIsRejected(): void
    {
        $fetcher = new Fetcher([
            'max_response_size' => 10,
            'handler' => HandlerStack::create(new MockHandler([new Response(200, [], str_repeat('x', 11))])),
        ]);

        $this->expectException(ResponseTooLargeException::class);
        $fetcher->get('https://example.com/');
    }

    public function testAnnouncedContentLengthLargerThanLimitIsRejected(): void
    {
        $fetcher = new Fetcher([
            'max_response_size' => 10,
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, ['Content-Length' => '5000'], 'small'),
            ])),
        ]);

        $this->expectException(ResponseTooLargeException::class);
        $fetcher->get('https://example.com/');
    }

    public function testBodyWithinLimitIsReturned(): void
    {
        $fetcher = new Fetcher([
            'max_response_size' => 10,
            'handler' => HandlerStack::create(new MockHandler([new Response(200, [], 'tiny')])),
        ]);

        $this->assertSame('tiny', (string)$fetcher->get('https://example.com/')->getBody());
    }

    public function testProxiesAreRotatedPerRequest(): void
    {
        $usedProxies = [];
        $handler = function (RequestInterface $request, array $options) use (&$usedProxies) {
            $usedProxies[] = $options['proxy'] ?? null;
            return Create::promiseFor(new Response(200));
        };

        $fetcher = new Fetcher([
            'concurrency' => 1,
            'handler' => HandlerStack::create($handler),
            'proxies' => ['http://p1.example.com:8080', 'http://p2.example.com:8080'],
        ]);
        $fetcher->fetchMany(
            ['https://example.com/1', 'https://example.com/2', 'https://example.com/3'],
            function () {
            }
        );

        $this->assertSame(
            ['http://p1.example.com:8080', 'http://p2.example.com:8080', 'http://p1.example.com:8080'],
            $usedProxies
        );
    }

    public function testRedirectToPrivateAddressIsBlocked(): void
    {
        $fetcher = new Fetcher([
            'block_private_ips' => true,
            'resolver' => fn(string $host) => ['93.184.216.34'],
            'handler' => HandlerStack::create(new MockHandler([
                new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
                new Response(200, [], 'secret'),
            ])),
        ]);

        $this->expectException(BlockedRequestException::class);
        $fetcher->get('https://example.com/');
    }

    public function testGuardPinsTheCheckedAddress(): void
    {
        $captured = [];
        $handler = function (RequestInterface $request, array $options) use (&$captured) {
            $captured = $options;
            return Create::promiseFor(new Response(200));
        };
        $guarded = PrivateNetworkGuard::middleware(fn(string $host) => ['93.184.216.34'])($handler);

        $guarded(new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/page'), [])->wait();

        $this->assertSame(['example.com:443:93.184.216.34'], $captured['curl'][CURLOPT_RESOLVE]);
    }

    public function testGuardRejectsHostsResolvingToPrivateAddresses(): void
    {
        $handler = fn() => Create::promiseFor(new Response(200));
        $guarded = PrivateNetworkGuard::middleware(fn(string $host) => ['93.184.216.34', '10.0.0.5'])($handler);

        $this->expectException(BlockedRequestException::class);
        $guarded(new \GuzzleHttp\Psr7\Request('GET', 'https://rebind.example.com/'), [])->wait();
    }

    /**
     * @dataProvider addressProvider
     */
    public function testIsPublicAddress(string $address, bool $expected): void
    {
        $this->assertSame($expected, PrivateNetworkGuard::isPublicAddress($address));
    }

    public static function addressProvider(): array
    {
        return [
            'public IPv4' => ['93.184.216.34', true],
            'public IPv6' => ['2606:2800:220:1:248:1893:25c8:1946', true],
            'loopback' => ['127.0.0.1', false],
            'private 10/8' => ['10.1.2.3', false],
            'private 192.168/16' => ['192.168.1.1', false],
            'private 172.16/12' => ['172.20.0.1', false],
            'link-local / metadata' => ['169.254.169.254', false],
            'unspecified' => ['0.0.0.0', false],
            'IPv6 loopback' => ['::1', false],
            'IPv6 unique local' => ['fd00::1', false],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1', false],
            'IPv4-mapped public' => ['::ffff:93.184.216.34', true],
            'not an address' => ['example.com', false],
        ];
    }
}
