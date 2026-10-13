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
    public const array BY_NAME = [
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

        // Characters, not bytes, and no mbstring requirement; the byte cut is the fallback that keeps the cap.
        $message = preg_match('/\\A.{0,500}/su', $message, $match) === 1 ? $match[0] : substr($message, 0, 500);

        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
        $class = self::BY_NAME[$name] ?? self::fallback($status);

        return new $class($response, $message, $errors);
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
