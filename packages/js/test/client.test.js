import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { A2zClient, A2zApiError, ENDPOINTS } from '../src/index.js';

function fakeFetch(status, body, headers = {}) {
  const calls = [];
  const fn = async (url, init) => {
    calls.push({ url, init });
    return new Response(typeof body === 'string' ? body : JSON.stringify(body), { status, headers: { 'content-type': 'application/json', ...headers } });
  };
  fn.calls = calls;
  return fn;
}

test('every endpoint has a typed method', () => {
  const c = new A2zClient({ fetch: fakeFetch(200, { ok: true }) });
  for (const e of ENDPOINTS) assert.equal(typeof c[e.group.replace(/-(.)/g, (_, x) => x.toUpperCase())][e.method], 'function', `${e.group}.${e.method}`);
  assert.ok(ENDPOINTS.length >= 60);
});

test('builds the URL, sends the key and site, and reads rate limits', async () => {
  const f = fakeFetch(200, { ok: true, result: 1 }, { 'X-RateLimit-Limit': '2000', 'X-RateLimit-Remaining': '1999', 'X-RateLimit-Reset': '1793232000', 'X-A2Z-Cache': 'HIT' });
  const c = new A2zClient({ apiKey: 'a2z_test', site: 'shop.example', fetch: f });
  const r = await c.dns.records({ domain: 'example.com', type: 'MX' });
  assert.deepEqual(r, { ok: true, result: 1 });
  assert.equal(f.calls[0].url, 'https://a2z.tools/public-api/v1/dns/records?domain=example.com&type=MX');
  assert.equal(f.calls[0].init.headers['X-API-Key'], 'a2z_test');
  assert.equal(f.calls[0].init.headers['X-A2Z-Site'], 'shop.example');
  assert.equal(c.lastRateLimit.limit, 2000);
  assert.equal(c.lastRateLimit.remaining, 1999);
  assert.equal(c.lastRateLimit.cached, true);
  assert.equal(c.lastRateLimit.reset.toISOString(), '2026-10-29T00:00:00.000Z');
});

test('omits empty optional parameters and refuses a missing required one', async () => {
  const f = fakeFetch(200, { ok: true });
  const c = new A2zClient({ fetch: f, baseUrl: 'http://localhost:1/public-api/v1/' });
  await c.ssl.certificate({ host: 'a2z.tools' });
  assert.equal(f.calls[0].url, 'http://localhost:1/public-api/v1/ssl/certificate?host=a2z.tools');
  await assert.rejects(() => c.email.dkim({ domain: 'x.com' }), e => e instanceof A2zApiError && e.code === 'missing_parameter');
  assert.equal(f.calls.length, 1);
});

test('turns API errors into A2zApiError with code and Retry-After', async () => {
  const c = new A2zClient({ fetch: fakeFetch(429, { ok: false, code: 'rate_limited', error: 'Too many requests' }, { 'Retry-After': '17' }) });
  await assert.rejects(() => c.seo.llmsTxt({ domain: 'x.com' }), e => {
    assert.equal(e.status, 429);
    assert.equal(e.code, 'rate_limited');
    assert.equal(e.retryAfter, 17);
    assert.equal(e.message, 'Too many requests');
    return true;
  });
});

test('a check that ran but found nothing is not an error', async () => {
  const c = new A2zClient({ fetch: fakeFetch(200, { ok: false, error: 'Not found (HTTP 404)' }) });
  const r = await c.seo.llmsTxt({ domain: 'example.com' });
  assert.equal(r.ok, false);
});

test('network failures surface as network_error', async () => {
  const c = new A2zClient({ fetch: async () => { throw new TypeError('fetch failed'); } });
  await assert.rejects(() => c.http.status({ url: 'https://x.com' }), e => e.code === 'network_error' && e.status === 0);
});

test('require() and import share one implementation', () => {
  const require = createRequire(import.meta.url);
  const cjs = require('../src/index.cjs');
  assert.equal(cjs.A2zClient, A2zClient);
});
