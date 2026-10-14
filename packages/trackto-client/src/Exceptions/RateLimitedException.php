<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * The team exceeded its rate limit (`rate_limited`, 429); the wait is rateLimit()->retryAfter.
 */
final class RateLimitedException extends ApiException {}
