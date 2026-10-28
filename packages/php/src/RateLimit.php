<?php

declare(strict_types=1);

namespace A2zTools\Api;

/** Limits reported by the most recent response. */
final class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly ?int $remaining,
        /** Next reset, 00:00 UTC. */
        public readonly ?\DateTimeImmutable $reset,
        /** True when the answer came from the API's cache. */
        public readonly bool $cached,
    ) {
    }
}
