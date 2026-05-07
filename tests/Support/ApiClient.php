<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Session-aware JSON client for /api (PHP session cookie).
 */
final class ApiClient
{
    private Client $http;

    private CookieJar $cookies;

    public function __construct(string $baseUrl)
    {
        $this->cookies = new CookieJar();
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'cookies' => $this->cookies,
            'http_errors' => false,
            'timeout' => 25,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @return array{status: int, json: ?array, body: string}
     */
    public function get(string $path): array
    {
        $response = $this->http->get(ltrim($path, '/'));

        return $this->normalizeResponse($response);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, json: ?array, body: string}
     */
    public function post(string $path, array $payload = []): array
    {
        $response = $this->http->post(ltrim($path, '/'), [
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $this->normalizeResponse($response);
    }

    public function clearCookies(): void
    {
        $this->cookies->clear();
    }

    /**
     * @return array{status: int, json: ?array, body: string}
     */
    private function normalizeResponse(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $json = null;
        if ($body !== '') {
            $decoded = json_decode($body, true);
            $json = is_array($decoded) ? $decoded : null;
        }

        return [
            'status' => $response->getStatusCode(),
            'json' => $json,
            'body' => $body,
        ];
    }
}
