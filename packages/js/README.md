# @a2ztools/api

Free client for the [A2Z Tools public API](https://a2z.tools/developers). It covers 68 checks for SSL certificates, DNS, WHOIS (RDAP), SPF, DKIM, DMARC, security headers, robots.txt, llms.txt and site health. It has no dependencies and runs on Node 18+, Deno, Bun, browsers and edge runtimes.

```bash
npm i @a2ztools/api
```

```js
import { A2zClient, A2zApiError } from '@a2ztools/api';

const a2z = new A2zClient({ apiKey: process.env.A2Z_API_KEY }); // key optional

const cert = await a2z.ssl.certificate({ host: 'example.com' });
const mx = await a2z.dns.records({ domain: 'example.com', type: 'MX' });

try {
  await a2z.email.dmarc({ domain: 'example.com' });
} catch (e) {
  if (e instanceof A2zApiError && e.code === 'rate_limited') console.log('retry in', e.retryAfter, 's');
}
console.log(a2z.lastRateLimit); // { limit, remaining, reset, cached }
```

Without a key you get 10 calls a minute and 100 a day. A free key from <https://a2z.tools/developers> gives 60 a minute and 2,000 a day.

MIT licensed. Full reference: <https://a2z.tools/developers>
