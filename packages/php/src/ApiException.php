<?php

declare(strict_types=1);

namespace A2zTools\Api;

/** An error answer from the API (4xx/5xx), or a request that never got one. */
final class ApiException extends \RuntimeException
{
    /**
     * @param int $status HTTP status, or 0 when there was no answer.
     * @param string|null $errorCode missing_parameter, invalid_key, key_suspended, key_revoked, key_required,
     *        rate_limited, daily_quota, target_rate_limited, api_disabled, network_error
     * @param int|null $retryAfter Seconds to wait before retrying (429 / 503).
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }
}
