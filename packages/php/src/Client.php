<?php

declare(strict_types=1);

namespace A2zTools\Api;

/**
 * Client for the A2Z Tools public API (https://a2z.tools/developers):
 * SSL, DNS, WHOIS, email authentication, HTTP headers, SEO and site-health checks.
 * No dependencies beyond ext-json; uses PHP streams.
 *
 *     $client = new Client(['api_key' => 'a2z_...']);   // the key is optional
 *     $cert = $client->ssl->certificate('example.com');
 *
 * Each check returns the decoded JSON answer as an array. 'ok' is false when the check
 * ran but found a problem; API errors throw ApiException.
 */
final class Client
{
    public const VERSION = '1.0.0';
    public const DEFAULT_BASE_URL = 'https://a2z.tools/public-api/v1';

    public readonly SslGroup $ssl;
    public readonly DnsGroup $dns;
    public readonly EmailGroup $email;
    public readonly DomainGroup $domain;
    public readonly HttpGroup $http;
    public readonly SeoGroup $seo;
    public readonly SiteGroup $site;

    private ?string $apiKey;
    private ?string $siteHost;
    private string $baseUrl;
    private float $timeout;
    /** @var callable|null fn(string $url, array $headers, float $timeout): array{status:int, headers:array<string,string>, body:string} */
    private $transport;
    private ?RateLimit $lastRateLimit = null;

    /**
     * @param array{api_key?: string, site?: string, base_url?: string, timeout?: float, transport?: callable} $options
     */
    public function __construct(array $options = [])
    {
        $this->apiKey = $options['api_key'] ?? null;
        $this->siteHost = $options['site'] ?? null;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = (float) ($options['timeout'] ?? 30.0);
        $this->transport = $options['transport'] ?? null;

        $this->ssl = new SslGroup($this);
        $this->dns = new DnsGroup($this);
        $this->email = new EmailGroup($this);
        $this->domain = new DomainGroup($this);
        $this->http = new HttpGroup($this);
        $this->seo = new SeoGroup($this);
        $this->site = new SiteGroup($this);
    }

    /** Limits from the most recent response. */
    public function lastRateLimit(): ?RateLimit
    {
        return $this->lastRateLimit;
    }

    /**
     * Calls any endpoint by group and name, e.g. call('ssl', 'certificate', ['host' => 'example.com']).
     *
     * @param array<string, string|int|null> $params
     * @return array<string, mixed>
     */
    public function call(string $group, string $name, array $params = []): array
    {
        $query = http_build_query(array_filter($params, static fn ($v) => $v !== null && $v !== ''), '', '&', PHP_QUERY_RFC3986);
        $url = $this->baseUrl . '/' . $group . '/' . $name . ($query !== '' ? '?' . $query : '');

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'a2ztools-php/' . self::VERSION,
        ];
        if ($this->apiKey) {
            $headers['X-API-Key'] = $this->apiKey;
        }
        if ($this->siteHost) {
            $headers['X-A2Z-Site'] = $this->siteHost;
        }

        $response = $this->transport
            ? ($this->transport)($url, $headers, $this->timeout)
            : self::streamTransport($url, $headers, $this->timeout);
        if ($response === null) {
            throw new ApiException("Request to {$group}/{$name} failed.", 0, 'network_error');
        }

        $h = array_change_key_case($response['headers'], CASE_LOWER);
        if (isset($h['x-ratelimit-limit'])) {
            $this->lastRateLimit = new RateLimit(
                (int) $h['x-ratelimit-limit'],
                isset($h['x-ratelimit-remaining']) ? (int) $h['x-ratelimit-remaining'] : null,
                isset($h['x-ratelimit-reset']) ? (new \DateTimeImmutable('@' . (int) $h['x-ratelimit-reset'])) : null,
                ($h['x-a2z-cache'] ?? '') === 'HIT',
            );
        }

        $body = json_decode($response['body'], true);
        if ($response['status'] >= 400) {
            throw new ApiException(
                is_array($body) && isset($body['error']) ? (string) $body['error'] : 'HTTP ' . $response['status'],
                $response['status'],
                is_array($body) && isset($body['code']) ? (string) $body['code'] : null,
                isset($h['retry-after']) ? (int) $h['retry-after'] : null,
            );
        }
        return is_array($body) ? $body : [];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, headers:array<string,string>, body:string}|null
     */
    private static function streamTransport(string $url, array $headers, float $timeout): ?array
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $lines),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        // PHP 8.4+ has a function for this; $http_response_header is deprecated in 8.5.
        $raw = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : ($http_response_header ?? null);
        if ($body === false || !is_array($raw)) {
            return null;
        }
        $status = 0;
        $out = [];
        foreach ($raw as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1]; // the last status line wins (after redirects)
                $out = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $out[trim($k)] = trim($v);
            }
        }
        return ['status' => $status, 'headers' => $out, 'body' => $body];
    }
}
