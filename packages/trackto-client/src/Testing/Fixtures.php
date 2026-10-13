<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Testing;

use InvalidArgumentException;
use Saloon\Http\Faking\MockResponse;

/**
 * Spec-valid mock response bodies for tests that use the SDK.
 */
final class Fixtures
{
    private const array ERROR_STATUS = [
        'validation_error' => 422,
        'unauthorized' => 401,
        'forbidden' => 403,
        'missing_user_agent' => 403,
        'unsupported_version' => 400,
        'not_found' => 404,
        'rate_limited' => 429,
        'server_error' => 500,
        'team_suspended' => 403,
        'bad_request' => 400,
    ];

    /**
     * A complete LinkResource body.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function link(array $overrides = []): array
    {
        return array_replace([
            'id' => 1,
            'slug' => 'abc123',
            'destination_url' => 'https://example.com/landing',
            'short_url' => 'https://track.to/abc123',
            'expires_at' => null,
            'created_at' => '2026-07-06T00:00:00+00:00',
            'folder' => ['id' => 1, 'name' => 'Campaigns'],
            'tags' => [['id' => 1, 'name' => 'launch', 'color' => '#2563eb']],
            'has_password' => false,
            'status' => 'active',
            'click_count' => 0,
        ], $overrides);
    }

    /**
     * An error envelope body. `errors` is only present when non-empty, as on the server.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, mixed>
     */
    public static function error(string $name, ?string $message = null, array $errors = [], ?int $status = null): array
    {
        $statusCode = $status ?? self::ERROR_STATUS[$name] ?? throw new InvalidArgumentException(
            sprintf('Unknown API error name "%s".', $name),
        );

        $envelope = [
            'name' => $name,
            'message' => $message ?? 'Mock '.str_replace('_', ' ', $name).'.',
            'statusCode' => $statusCode,
        ];

        return $errors === [] ? $envelope : $envelope + ['errors' => $errors];
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>  $headers
     */
    public static function errorResponse(string $name, array $errors = [], array $headers = [], ?int $status = null): MockResponse
    {
        $body = self::error($name, errors: $errors, status: $status);

        return MockResponse::make($body, $body['statusCode'], $headers);
    }
}
