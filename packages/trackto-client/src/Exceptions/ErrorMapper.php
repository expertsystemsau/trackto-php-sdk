<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

use JsonException;
use Saloon\Http\Response;

/**
 * Turns a failed response into the exception named by the error envelope's `name`.
 * Status is only the fallback for unknown names: 403 carries three codes and 400 carries two.
 */
final class ErrorMapper
{
    /** @var array<string, class-string<ApiException>> */
    public const BY_NAME = [
        'validation_error' => ValidationException::class,
        'unauthorized' => UnauthorizedException::class,
        'forbidden' => ForbiddenException::class,
        'missing_user_agent' => MissingUserAgentException::class,
        'unsupported_version' => UnsupportedVersionException::class,
        'not_found' => NotFoundException::class,
        'rate_limited' => RateLimitedException::class,
        'server_error' => ServerException::class,
        'team_suspended' => TeamSuspendedException::class,
        'bad_request' => BadRequestException::class,
    ];

    private const MAX_ENTRIES = 100;

    public static function map(Response $response): ApiException
    {
        $status = $response->status();

        try {
            // Saloon's json() throws on HTML and TypeError on scalar JSON.
            $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $body = null;
        }

        $name = is_array($body) ? ($body['name'] ?? null) : null;
        $message = is_array($body) ? ($body['message'] ?? null) : null;

        if (! is_string($name) || $name === '' || ! is_string($message)) {
            return new UnexpectedResponseException(
                $response,
                sprintf('Unexpected response from the track.to API (HTTP %d).', $status),
            );
        }

        $message = self::cap($message);

        $errors = is_array($body['errors'] ?? null) ? self::capValue($body['errors']) : [];

        $class = self::BY_NAME[$name] ?? self::fallback($status);

        return new $class($response, $message, $errors);
    }

    /** Bounds hostile field errors like the message: strings at 500 characters, every list or map at 100 entries, nested to any depth. */
    private static function capValue(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => self::cap($value),
            is_array($value) => array_map(self::capValue(...), array_slice($value, 0, self::MAX_ENTRIES, true)),
            default => $value,
        };
    }

    /** Characters, not bytes, and no mbstring requirement; the byte cut is the fallback that keeps the cap. */
    private static function cap(string $text): string
    {
        return preg_match('/\\A.{0,500}/su', $text, $match) === 1 ? $match[0] : substr($text, 0, 500);
    }

    /**
     * @return class-string<ApiException>
     */
    public static function fallback(int $status): string
    {
        return match (true) {
            $status === 401 => UnauthorizedException::class,
            $status === 403 => ForbiddenException::class,
            $status === 404 => NotFoundException::class,
            $status === 422 => ValidationException::class,
            $status === 429 => RateLimitedException::class,
            $status >= 500 => ServerException::class,
            $status >= 400 => BadRequestException::class,
            default => UnexpectedResponseException::class,
        };
    }
}
