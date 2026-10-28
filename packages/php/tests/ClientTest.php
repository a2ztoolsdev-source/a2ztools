<?php

declare(strict_types=1);

namespace A2zTools\Api\Tests;

use A2zTools\Api\ApiException;
use A2zTools\Api\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var list<array{url:string, headers:array<string,string>}> */
    private array $requests = [];

    private function client(int $status, array $body, array $headers = [], array $options = []): Client
    {
        $this->requests = [];
        return new Client($options + ['transport' => function (string $url, array $h) use ($status, $body, $headers) {
            $this->requests[] = ['url' => $url, 'headers' => $h];
            return ['status' => $status, 'headers' => $headers, 'body' => json_encode($body)];
        }]);
    }

    public function testUrlHeadersAndRateLimit(): void
    {
        $c = $this->client(200, ['ok' => true, 'domain' => 'example.com'],
            ['X-RateLimit-Limit' => '2000', 'X-RateLimit-Remaining' => '1999', 'X-RateLimit-Reset' => '1793232000', 'X-A2Z-Cache' => 'HIT'],
            ['api_key' => 'a2z_test', 'site' => 'shop.example']);
        $r = $c->dns->records('example.com', 'MX');
        self::assertSame('example.com', $r['domain']);
        self::assertSame('https://a2z.tools/public-api/v1/dns/records?domain=example.com&type=MX', $this->requests[0]['url']);
        self::assertSame('a2z_test', $this->requests[0]['headers']['X-API-Key']);
        self::assertSame('shop.example', $this->requests[0]['headers']['X-A2Z-Site']);
        self::assertSame(2000, $c->lastRateLimit()->limit);
        self::assertSame(1999, $c->lastRateLimit()->remaining);
        self::assertTrue($c->lastRateLimit()->cached);
        self::assertSame('2026-10-29T00:00:00+00:00', $c->lastRateLimit()->reset->format(DATE_ATOM));
    }

    public function testOptionalParametersAreLeftOut(): void
    {
        $c = $this->client(200, ['ok' => true], [], ['base_url' => 'http://localhost:1/public-api/v1/']);
        $c->ssl->certificate('a2z.tools');
        $c->email->security('google.com', 'google');
        self::assertSame('http://localhost:1/public-api/v1/ssl/certificate?host=a2z.tools', $this->requests[0]['url']);
        self::assertStringEndsWith('/email/security?domain=google.com&dkimSelector=google', $this->requests[1]['url']);
    }

    public function testErrorsThrowWithCodeAndRetryAfter(): void
    {
        $c = $this->client(429, ['ok' => false, 'code' => 'rate_limited', 'error' => 'Too many requests'], ['Retry-After' => '17']);
        try {
            $c->seo->llmsTxt('x.com');
            self::fail('expected an exception');
        } catch (ApiException $e) {
            self::assertSame(429, $e->status);
            self::assertSame('rate_limited', $e->errorCode);
            self::assertSame(17, $e->retryAfter);
            self::assertSame('Too many requests', $e->getMessage());
        }
    }

    public function testACheckThatFoundNothingIsNotAnError(): void
    {
        $c = $this->client(200, ['ok' => false, 'error' => 'Not found (HTTP 404)']);
        self::assertFalse($c->seo->llmsTxt('example.com')['ok']);
    }

    public function testNetworkFailure(): void
    {
        $c = new Client(['transport' => fn () => null]);
        $this->expectException(ApiException::class);
        $c->http->status('https://x.com');
    }
}
