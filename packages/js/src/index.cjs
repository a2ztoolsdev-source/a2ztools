'use strict';
// @a2ztools/api - client for the A2Z Tools public API (https://a2z.tools/developers).
// Zero dependencies; needs a global fetch (Node 18+, Deno, Bun, browsers, edge runtimes).

const { ENDPOINTS } = require('./endpoints.cjs');

const VERSION = '1.0.1';
const DEFAULT_BASE_URL = 'https://a2z.tools/public-api/v1';

class A2zApiError extends Error {
  constructor(message, { status, code, retryAfter, body } = {}) {
    super(message);
    this.name = 'A2zApiError';
    /** HTTP status, or 0 when the request never got an answer. */
    this.status = status || 0;
    /** API error code: missing_parameter, invalid_key, rate_limited, daily_quota, ... */
    this.code = code || null;
    /** Seconds to wait before retrying, from Retry-After (429 / 503). */
    this.retryAfter = retryAfter == null ? null : retryAfter;
    this.body = body;
  }
}

function camel(s) {
  return s.replace(/-([a-z0-9])/g, (_, c) => c.toUpperCase());
}

function readRateLimit(headers) {
  const n = name => {
    const v = headers.get(name);
    return v == null || v === '' ? null : Number(v);
  };
  const limit = n('x-ratelimit-limit');
  if (limit == null) return null;
  const reset = n('x-ratelimit-reset');
  return {
    limit,
    remaining: n('x-ratelimit-remaining'),
    reset: reset == null ? null : new Date(reset * 1000),
    cached: headers.get('x-a2z-cache') === 'HIT',
  };
}

class A2zClient {
  /**
   * @param {object} [options]
   * @param {string} [options.apiKey]   Free key from https://a2z.tools/developers. Optional.
   * @param {string} [options.site]     Your website's host, sent as X-A2Z-Site (for plugins without a key).
   * @param {string} [options.baseUrl]  Defaults to https://a2z.tools/public-api/v1.
   * @param {number} [options.timeoutMs] Per-request timeout, default 30000.
   * @param {Function} [options.fetch]  A fetch implementation, default globalThis.fetch.
   */
  constructor(options = {}) {
    this._apiKey = options.apiKey || null;
    this._site = options.site || null;
    this.baseUrl = (options.baseUrl || DEFAULT_BASE_URL).replace(/\/+$/, '');
    this.timeoutMs = options.timeoutMs || 30000;
    this._fetch = options.fetch || (typeof fetch === 'function' ? fetch.bind(globalThis) : null);
    if (!this._fetch) throw new Error('No fetch available: pass options.fetch or use Node 18+.');
    /** Limits from the most recent response, or null. */
    this.lastRateLimit = null;

    for (const e of ENDPOINTS) {
      const group = camel(e.group);
      if (!this[group]) this[group] = {};
      this[group][e.method] = (params, opts) => this.call(e.group, e.name, params, opts);
    }
  }

  /** Calls any endpoint: call('ssl', 'certificate', { host: 'example.com' }). */
  async call(group, name, params = {}, opts = {}) {
    const def = ENDPOINTS.find(e => e.group === group && e.name === name);
    const query = new URLSearchParams();
    if (def) {
      for (const [p, required] of def.params) {
        const v = params[p];
        if (v === undefined || v === null || v === '') {
          if (required) throw new A2zApiError(`The '${p}' parameter is required for ${group}/${name}.`, { code: 'missing_parameter' });
          continue;
        }
        query.set(p, String(v));
      }
    } else {
      for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== null) query.set(k, String(v));
    }

    const headers = { Accept: 'application/json' };
    if (this._apiKey) headers["X-API-Key"] = this._apiKey;
    if (this._site) headers["X-A2Z-Site"] = this._site;
    // A browser refuses to set User-Agent; elsewhere it identifies the SDK in our logs.
    if (typeof window === 'undefined') headers['User-Agent'] = `a2ztools-js/${VERSION}`;

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    if (opts.signal) opts.signal.addEventListener('abort', () => controller.abort(), { once: true });

    let res;
    try {
      const qs = query.toString();
      res = await this._fetch(`${this.baseUrl}/${group}/${name}${qs ? '?' + qs : ''}`, { headers, signal: controller.signal });
    } catch (err) {
      throw new A2zApiError(`Request to ${group}/${name} failed: ${err && err.message ? err.message : err}`, { status: 0, code: 'network_error' });
    } finally {
      clearTimeout(timer);
    }

    this.lastRateLimit = readRateLimit(res.headers);
    const text = await res.text();
    let body = null;
    try { body = text ? JSON.parse(text) : null; } catch (_) { /* not JSON */ }

    if (!res.ok) {
      const ra = res.headers.get('retry-after');
      throw new A2zApiError((body && body.error) || `HTTP ${res.status}`, {
        status: res.status,
        code: body && body.code,
        retryAfter: ra == null ? null : Number(ra),
        body,
      });
    }
    return body;
  }
}

module.exports = { A2zClient, A2zApiError, ENDPOINTS, VERSION, DEFAULT_BASE_URL };
module.exports.default = A2zClient;
