<?php

declare(strict_types=1);

/**
 * Router for PHP built-in server (integration tests & local dev without Apache rewrite).
 *
 *   cd project-root
 *   php -S 127.0.0.1:8888 server-router.php
 *
 * API: http://127.0.0.1:8888/api/health
 * Static: http://127.0.0.1:8888/index.html
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uri) || $uri === '') {
    $uri = '/';
}

$docroot = __DIR__;
$localFile = $docroot . $uri;

if ($uri !== '/' && is_file($localFile)) {
    return false;
}

if (str_starts_with($uri, '/api')) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require $docroot . '/api/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found: ' . $uri;

return true;
