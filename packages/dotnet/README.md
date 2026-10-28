# A2zTools.Api

Free .NET client for the [A2Z Tools public API](https://a2z.tools/developers). It covers 68 checks for SSL certificates, DNS, WHOIS (RDAP), SPF, DKIM, DMARC, security headers, robots.txt, llms.txt and site health. It targets netstandard2.0 and net8.0.

```bash
dotnet add package A2zTools.Api
```

```csharp
using A2zTools.Api;

using var a2z = new A2zClient(new A2zClientOptions { ApiKey = "a2z_..." }); // key optional

var cert = await a2z.Ssl.CertificateAsync("example.com");
var mx = await a2z.Dns.RecordsAsync("example.com", "MX");
Console.WriteLine(mx.Data);

try { await a2z.Email.DmarcAsync("example.com"); }
catch (A2zApiException ex) when (ex.Code == "rate_limited") { /* wait ex.RetryAfterSeconds */ }
```

With IHttpClientFactory, use `new A2zClient(httpClient, options)`.

Without a key you get 10 calls a minute and 100 a day. A free key from <https://a2z.tools/developers> gives 60 a minute and 2,000 a day.

MIT licensed.
