# a2ztools/api

Free PHP 8.1+ client for the [A2Z Tools public API](https://a2z.tools/developers). It covers 68 checks for SSL certificates, DNS, WHOIS (RDAP), SPF, DKIM, DMARC, security headers, robots.txt, llms.txt and site health. It needs only ext-json and no HTTP library.

```bash
composer require a2ztools/api
```

```php
use A2zTools\Api\Client;
use A2zTools\Api\ApiException;

$a2z = new Client(['api_key' => getenv('A2Z_API_KEY')]); // key optional
$cert = $a2z->ssl->certificate('example.com');
$mx = $a2z->dns->records('example.com', 'MX');

try {
    $a2z->email->dmarc('example.com');
} catch (ApiException $e) {
    echo $e->status, ' ', $e->errorCode, ' ', $e->retryAfter;
}
```

MIT licensed.
