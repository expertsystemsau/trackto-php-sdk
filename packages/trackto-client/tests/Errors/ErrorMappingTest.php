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

it('caps a server message at 500 characters, counting characters not bytes', function (string $message, string $expected): void {
    expect(TestClient::failure(MockResponse::make(Fixtures::error('not_found', message: $message), 404))->getMessage())->toBe($expected);
})->with([
    'ascii' => [str_repeat('a', 2000), str_repeat('a', 500)],
    'multibyte' => [str_repeat('é', 2000), str_repeat('é', 500)],
    'exactly 500' => [str_repeat('b', 500), str_repeat('b', 500)],
    'short' => ['Link not found.', 'Link not found.'],
]);

it('caps every string field-error message like the message, leaving other entries alone', function (): void {
    $errors = [
        'url' => [str_repeat('a', 500), str_repeat('b', 501), 42, ['nested']],
        'slug' => [str_repeat('é', 2000), str_repeat("\u{1F600}", 501), 'short'],
    ];
    $e = TestClient::failure(Fixtures::errorResponse('validation_error', errors: $errors));

    expect($e->errors()['url'][0])->toBe(str_repeat('a', 500))
        ->and($e->errors()['url'][1])->toBe(str_repeat('b', 500))
        ->and($e->errors()['url'][2])->toBe(42)
        ->and($e->errors()['url'][3])->toBe(['nested'])
        ->and($e->errors()['slug'][0])->toBe(str_repeat('é', 500))
        ->and(preg_match('//u', $e->errors()['slug'][0]))->toBe(1)
        ->and($e->errors()['slug'][1])->toBe(str_repeat("\u{1F600}", 500))
        ->and($e->errors()['slug'][2])->toBe('short');
});

it('caps scalar, nested and oversized field errors too', function (): void {
    $errors = ['url' => str_repeat('a', 2000), 'deep' => [['x' => str_repeat('b', 501)]]];
    foreach (range(1, 150) as $i) {
        $errors["f$i"] = ['bad'];
    }
    $e = TestClient::failure(Fixtures::errorResponse('validation_error', errors: $errors));

    expect($e->errors()['url'])->toBe(str_repeat('a', 500))
        ->and($e->errors()['deep'][0]['x'])->toBe(str_repeat('b', 500))
        ->and($e->errors())->toHaveCount(100)
        ->and($e->errorsFor('url'))->toBe([]);
});

it('keeps only 100 messages per field', function (): void {
    $e = TestClient::failure(Fixtures::errorResponse('validation_error', errors: ['many' => range(1, 150)]));

    expect($e->errors()['many'])->toHaveCount(100);
});
