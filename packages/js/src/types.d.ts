export interface A2zClientOptions {
  /** Free key from https://a2z.tools/developers. Optional: without one you get 10 calls a minute, 100 a day. */
  apiKey?: string;
  /** Your website's host (example.com), sent as X-A2Z-Site. For plugins that call without a key. */
  site?: string;
  /** Defaults to https://a2z.tools/public-api/v1. */
  baseUrl?: string;
  /** Per-request timeout in milliseconds. Default 30000. */
  timeoutMs?: number;
  /** A fetch implementation. Default: globalThis.fetch. */
  fetch?: typeof fetch;
}

/** Every check answers with ok plus its own fields. ok is false when the check ran but found a problem (for example no record). */
export interface A2zResult {
  ok: boolean;
  error?: string | null;
  [key: string]: unknown;
}

export interface RateLimit {
  limit: number;
  remaining: number | null;
  /** Next reset, 00:00 UTC. */
  reset: Date | null;
  /** True when the answer came from the API's cache. */
  cached: boolean;
}
