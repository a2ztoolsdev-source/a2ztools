# A2Z Tools: free website-check API, SDKs and plugins

Official open-source clients for the **[A2Z Tools public API](https://a2z.tools/developers)**: 68 free checks for SSL certificates, DNS, WHOIS (RDAP), email authentication (SPF, DKIM, DMARC, BIMI, MTA-STS), HTTP and security headers, SEO and AI-crawler files (robots.txt, sitemaps, llms.txt, ads.txt) and overall site health.

You can start without a key. A free key from [a2z.tools/developers](https://a2z.tools/developers) raises the limits.

| Access | Per minute | Per day |
|---|---|---|
| No key (per IP) | 10 | 100 |
| Free key | 60 | 2,000 |

## Packages

| Language | Package | Install |
|---|---|---|
| JavaScript / TypeScript | [`@a2ztools/api`](packages/js) | `npm i @a2ztools/api` |
| Python | [`a2ztools`](packages/python) | `pip install a2ztools` |
| .NET | [`A2zTools.Api`](packages/dotnet) | `dotnet add package A2zTools.Api` |
| PHP | [`a2ztools/api`](packages/php) | `composer require a2ztools/api` |
| WordPress | [A2Z Widgets](integrations/wordpress/a2z-widgets) | calculators, converters and status badges as a block or shortcode |

The OpenAPI 3.1 description is in [`openapi/openapi.json`](openapi/openapi.json) and always live at <https://a2z.tools/public-api/v1/openapi.json>.

## Quick start

```bash
curl -H "X-API-Key: YOUR_KEY" "https://a2z.tools/public-api/v1/ssl/certificate?host=example.com"
```

```js
import { A2zClient } from '@a2ztools/api';
const a2z = new A2zClient({ apiKey: process.env.A2Z_API_KEY });   // key optional
const dmarc = await a2z.email.dmarc({ domain: 'example.com' });
```

```python
from a2ztools import A2zClient
a2z = A2zClient()                       # key optional
print(a2z.dns.records("example.com", type="MX"))
```

```csharp
using var a2z = new A2zClient(new A2zClientOptions { ApiKey = "..." });
var cert = await a2z.Ssl.CertificateAsync("example.com");
```

```php
$a2z = new A2zTools\Api\Client(['api_key' => getenv('A2Z_API_KEY')]);
$spf = $a2z->email->spf('example.com');
```

Every check returns JSON with `ok` plus its own fields. `ok: false` means the check ran but found a problem, for example no DMARC record. Errors such as a bad key or a rate limit raise an exception with `status`, `code` and `retryAfter`.

## Endpoint groups

`ssl` (certificate, protocols, https, https-redirect, hsts, hsts-preload, ocsp, crl, ciphers, certificate-transparency) ·
`dns` (records, propagation, reverse, dnssec) ·
`email` (security, spf, dmarc, dkim, bimi, mta-sts, tls-rpt) ·
`domain` (whois, ip, asn, reverse-ip) ·
`http` (headers, status, redirects, security-headers, csp, cors, cookies, and more) ·
`seo` (open-graph, canonical, hreflang, schema, robots-txt, sitemap, llms-txt, ai-crawlers, ads-txt, and more) ·
`site` (health, audit, security-txt, manifest, favicon, dependencies, vulnerable-libraries, and more).

Full reference with live examples: **<https://a2z.tools/developers>**

## Development

The per-endpoint code in every SDK is generated from the spec:

```bash
node scripts/generate.mjs          # regenerate after openapi/openapi.json changes
node scripts/generate.mjs --check  # CI: fails if anything is stale
```

Tests: `npm test` in `packages/js`, `pytest` in `packages/python`, `dotnet test` in `packages/dotnet`, `vendor/bin/phpunit` at the root (PHP).

## Fair use and attribution

Only check sites you own or have a legitimate reason to check. If you show the results publicly, please credit *Data by [A2Z Tools](https://a2z.tools)*.

A2Z Tools is a product of Reliable Software Technology. SDKs are MIT licensed. The WordPress plugin is GPL-2.0-or-later. Contact: app@a2z.tools.
