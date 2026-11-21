<?php

// Router for PHP's built-in web server, used by FetcherIntegrationTest.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

switch ($path) {
    case '/ok':
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><body><a href="/other">other</a></body></html>';
        break;

    case '/big-streamed':
        // 2 MB without Content-Length
        header('Content-Type: text/html');
        $chunk = str_repeat('x', 65536);
        for ($i = 0; $i < 32; $i++) {
            echo $chunk;
            flush();
        }
        break;

    case '/big-announced':
        header('Content-Type: text/html');
        header('Content-Length: 50000000');
        echo 'only a few bytes';
        break;

    case '/gzip-bomb':
        // ~5 KB on the wire, 5 MB after decompression
        $body = gzencode(str_repeat('a', 5 * 1024 * 1024), 9);
        header('Content-Type: text/html');
        header('Content-Encoding: gzip');
        header('Content-Length: ' . strlen($body));
        echo $body;
        break;

    case '/redirect':
        header('Location: /ok', true, 302);
        break;

    default:
        http_response_code(404);
        echo 'not found';
}

return true;
