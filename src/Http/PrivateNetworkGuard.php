<?php

namespace IProDev\Sitemap\Http;

use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware that refuses requests to private, loopback, link-local and
 * other non-public addresses (SSRF protection).
 *
 * Push it onto the handler stack *after* the redirect middleware (HandlerStack::push
 * appends to the inside of the stack) so every redirect hop is checked too. The
 * checked address is pinned with CURLOPT_RESOLVE so cURL cannot resolve the host
 * to a different address afterwards (DNS rebinding).
 */
final class PrivateNetworkGuard
{
    /**
     * @param callable(string): string[]|null $resolver returns the IP addresses of a host name
     */
    public static function middleware(?callable $resolver = null): callable
    {
        $resolver = $resolver ?? [self::class, 'resolve'];

        return static function (callable $handler) use ($resolver): callable {
            return static function (RequestInterface $request, array $options) use ($handler, $resolver) {
                $uri = $request->getUri();
                $host = trim($uri->getHost(), '[]');

                if ($host === '') {
                    return Create::rejectionFor(new BlockedRequestException('Request has no host'));
                }

                $isLiteralIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
                $addresses = $isLiteralIp ? [$host] : $resolver($host);

                if (empty($addresses)) {
                    return Create::rejectionFor(new BlockedRequestException("Could not resolve host {$host}"));
                }

                foreach ($addresses as $address) {
                    if (!self::isPublicAddress($address)) {
                        return Create::rejectionFor(new BlockedRequestException(
                            "Blocked request to {$host} ({$address}): private or reserved address"
                        ));
                    }
                }

                if (!$isLiteralIp && defined('CURLOPT_RESOLVE')) {
                    $port = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);
                    $address = $addresses[0];
                    $pinned = str_contains($address, ':') ? "[{$address}]" : $address;
                    $options['curl'][CURLOPT_RESOLVE] = ["{$host}:{$port}:{$pinned}"];
                }

                return $handler($request, $options);
            };
        };
    }

    /**
     * Whether an IP address is publicly routable
     */
    public static function isPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }

        // IPv4-mapped (::ffff:a.b.c.d) and IPv4-compatible IPv6 addresses: check the embedded IPv4 address
        if (strlen($packed) === 16 && substr($packed, 0, 10) === str_repeat("\0", 10)) {
            $prefix = substr($packed, 10, 2);
            if ($prefix === "\xff\xff" || $prefix === "\0\0") {
                $ipv4 = inet_ntop(substr($packed, 12));
                return $ipv4 !== false && $ipv4 !== '0.0.0.0' && self::isPublicAddress($ipv4);
            }
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
            // PHP 8.2+: also excludes carrier-grade NAT, documentation and benchmark ranges
            $flags |= FILTER_FLAG_GLOBAL_RANGE;
        }

        return filter_var($address, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Resolve the IPv4 and IPv6 addresses of a host
     *
     * @return string[]
     */
    public static function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
