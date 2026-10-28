export type { A2zClientOptions, A2zResult, RateLimit } from './types.js';
export * from './client.js';
import { A2zClient } from './client.js';

export declare class A2zApiError extends Error {
  /** HTTP status, or 0 when the request never got an answer. */
  readonly status: number;
  /** missing_parameter, invalid_key, key_suspended, key_revoked, key_required, rate_limited, daily_quota, target_rate_limited, api_disabled, network_error */
  readonly code: string | null;
  /** Seconds to wait before retrying (429 / 503). */
  readonly retryAfter: number | null;
  readonly body: unknown;
}

export declare const ENDPOINTS: ReadonlyArray<{ group: string; name: string; method: string; params: Array<[string, 0 | 1]> }>;
export declare const VERSION: string;
export declare const DEFAULT_BASE_URL: string;
export default A2zClient;
