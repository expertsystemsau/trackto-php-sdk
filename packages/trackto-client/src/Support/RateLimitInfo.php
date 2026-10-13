<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Support;

use DateTimeImmutable;
use DateTimeZone;
use Saloon\Http\Response;

/**
 * Rate-limit timing parsed from RateLimit-Policy, RateLimit and Retry-After. Every field is
 * null when the server did not send it.
 */
final readonly class RateLimitInfo
{
    public function __construct(
        public ?int $limit,
        public ?int $remaining,
        public ?int $reset,
        public ?int $retryAfter,
    ) {}

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public static function fromHeaders(array $headers, ?int $now = null): self
    {
        $lower = self::lowerCase($headers);

        $limit = self::match($lower['ratelimit-policy'] ?? '', '/;\s*q=(\d+)/');
        $remaining = self::match($lower['ratelimit'] ?? '', '/;\s*r=(\d+)/');
        $reset = self::match($lower['ratelimit'] ?? '', '/;\s*t=(\d+)/');

        // With no budget left, the reset is the wait.
        $retryAfter = self::retryAfter($lower['retry-after'] ?? '', $now) ?? ($remaining === 0 ? $reset : null);

        return new self($limit, $remaining, $reset, $retryAfter);
    }

    public static function fromResponse(Response $response): self
    {
        return self::fromHeaders($response->headers()->all());
    }

    /**
     * Only the Retry-After header, never the RateLimit reset: on a 5xx the reset describes a quota, not an outage.
     */
    public static function retryAfterHeader(Response $response): ?int
    {
        return self::retryAfter(self::lowerCase($response->headers()->all())['retry-after'] ?? '', null);
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>
     */
    private static function lowerCase(array $headers): array
    {
        $lower = [];

        foreach ($headers as $name => $value) {
            $lower[strtolower($name)] = is_array($value) ? (string) ($value[0] ?? '') : $value;
        }

        return $lower;
    }

    private static function match(string $header, string $pattern): ?int
    {
        return preg_match($pattern, $header, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Digit seconds or an IMF-fixdate only: PHP's free-form date parser turns "-5" or "1.5" into an immediate retry.
     */
    private static function retryAfter(string $value, ?int $now): ?int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }

        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new DateTimeZone('UTC'));

        return $date === false ? null : max(0, $date->getTimestamp() - ($now ?? time()));
    }
}
