# a2ztools

Free Python client for the [A2Z Tools public API](https://a2z.tools/developers). It covers 68 checks for SSL certificates, DNS, WHOIS (RDAP), SPF, DKIM, DMARC, security headers, robots.txt, llms.txt and site health. It uses only the standard library and needs Python 3.9+.

```bash
pip install a2ztools
```

```python
from a2ztools import A2zClient, A2zApiError

a2z = A2zClient("a2z_...")          # key optional
cert = a2z.ssl.certificate("example.com")
mx = a2z.dns.records("example.com", type="MX")

try:
    a2z.email.dmarc("example.com")
except A2zApiError as e:
    print(e.status, e.code, e.retry_after)

print(a2z.last_rate_limit)
```

Without a key you get 10 calls a minute and 100 a day. A free key from <https://a2z.tools/developers> gives 60 a minute and 2,000 a day.

MIT licensed.
