<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions as E;
use ExpertSystems\TrackTo\Support\RateLimitInfo;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;

it('maps each envelope name to its exact exception class', function (string $name, int $status, string $class): void {
    $e = TestClient::failure(Fixtures::errorResponse($name));

    expect($e::class)->toBe($class)
        ->and($e)->toBeInstanceOf(E\TrackToException::class)
        ->and($e)->toBeInstanceOf(RequestException::class)
        ->and($e->status())->toBe($status)
        ->and($e->getMessage())->toBe(Fixtures::error($name)['message'])
        ->and($e->rateLimit())->toBeInstanceOf(RateLimitInfo::class);
})->with([
    ['validation_error', 422, E\ValidationException::class],
    ['unauthorized', 401, E\UnauthorizedException::class],
    ['forbidden', 403, E\ForbiddenException::class],
    ['missing_user_agent', 403, E\MissingUserAgentException::class],
    ['team_suspended', 403, E\TeamSuspendedException::class],
    ['unsupported_version', 400, E\UnsupportedVersionException::class],
    ['bad_request', 400, E\BadRequestException::class],
    ['not_found', 404, E\NotFoundException::class],
    ['rate_limited', 429, E\RateLimitedException::class],
    ['server_error', 500, E\ServerException::class],
]);

it('exposes field errors', function (): void {
    $e = TestClient::failure(Fixtures::errorResponse('validation_error', ['destination_url' => ['The destination url field must be a valid URL.']]));

    expect($e->errorsFor('destination_url'))->toBe(['The destination url field must be a valid URL.'])
        ->and($e->errorsFor('slug'))->toBe([])
        ->and($e->errors())->toBe(['destination_url' => ['The destination url field must be a valid URL.']]);
});

it('reads list errors on any code and null errors as empty', function (): void {
    expect(TestClient::failure(Fixtures::errorResponse('not_found', ['slugs' => ['a', 'b']]))->errorsFor('slugs'))->toBe(['a', 'b'])
        ->and(TestClient::failure(MockResponse::make('{"name":"not_found","message":"x","errors":null}', 404))->errors())->toBe([]);
});

it('carries Retry-After on the rate limit info', function (): void {
    $e = TestClient::failure(Fixtures::errorResponse('rate_limited', headers: ['Retry-After' => '10']));

    expect($e->rateLimit()->retryAfter)->toBe(10)
        ->and(method_exists($e, 'retryAfter'))->toBeFalse();
});
