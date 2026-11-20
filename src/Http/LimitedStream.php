<?php

namespace IProDev\Sitemap\Http;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Response body sink that stops accepting data after a size limit.
 *
 * It receives the *decoded* body (after gzip/deflate), so it also protects
 * against decompression bombs and responses without a Content-Length. When the
 * limit is reached write() returns 0, which makes cURL abort the transfer.
 */
final class LimitedStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var StreamInterface */
    private $stream;

    private int $limit;
    private int $written = 0;
    private bool $exceeded = false;

    public function __construct(int $limit, ?StreamInterface $stream = null)
    {
        $this->limit = $limit;
        $this->stream = $stream ?? Utils::streamFor(Utils::tryFopen('php://temp', 'r+'));
    }

    public function write($string): int
    {
        $length = strlen($string);
        if ($this->limit > 0 && $this->written + $length > $this->limit) {
            $this->exceeded = true;
            return 0;
        }

        $this->written += $length;
        return $this->stream->write($string);
    }

    public function limitExceeded(): bool
    {
        return $this->exceeded;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
