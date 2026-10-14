<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions as E;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockResponse;

it('maps an unknown error name by status', function (int $status, string $class): void {
    $e = TestClient::failure(MockResponse::make(['name' => 'payment_required', 'message' => 'Pay up.', 'statusCode' => $status], $status));

    expect($e::class)->toBe($class)->and($e->getMessage())->toBe('Pay up.');
})->with([
    [401, E\UnauthorizedException::class],
    [403, E\ForbiddenException::class],
    [404, E\NotFoundException::class],
    [422, E\ValidationException::class],
    [429, E\RateLimitedException::class],
    [500, E\ServerException::class],
    [503, E\ServerException::class],
    [400, E\BadRequestException::class],
    [409, E\BadRequestException::class],
]);

it('raises UnexpectedResponseException for a body that is not an envelope', function (string $body, int $status, array $headers): void {
    $e = TestClient::failure(MockResponse::make($body, $status, $headers));

    expect($e)->toBeInstanceOf(E\UnexpectedResponseException::class)
        ->and($e->status())->toBe($status)
        ->and($e->getMessage())->not->toContain($body === '' ? "\0" : trim($body, '"'));
})->with([
    'empty' => ['', 502, []],
    'empty object' => ['{}', 500, []],
    'html' => ['<html><body>Bad gateway</body></html>', 502, ['Content-Type' => 'text/html']],
    'null' => ['null', 500, []],
    'string' => ['"oops"', 500, []],
    'number' => ['123', 500, []],
    'empty list' => ['[]', 500, []],
    'no message' => ['{"name":"not_found"}', 404, []],
    'empty name' => ['{"name":"","message":"x"}', 404, []],
]);

it('treats a redirect as a failure', function (): void {
    $e = TestClient::failure(MockResponse::make('', 302, ['Location' => 'https://evil.example/']));

    expect($e)->toBeInstanceOf(E\UnexpectedResponseException::class)->and($e->status())->toBe(302);
});
