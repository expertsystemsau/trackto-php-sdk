<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\RecordingSleeper;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * @param  array<string, string>  $headers
 */
function serverRetryOutage(array $headers = []): MockResponse
{
    return Fixtures::errorResponse('server_error', headers: $headers, status: 503);
}

function serverRetryRecovery(): MockResponse
{
    return MockResponse::make(['data' => Fixtures::link()], 200);
}

it('waits the Retry-After of a read 5xx when it fits the cap', function (): void {
    $mock = new MockClient([serverRetryOutage(['Retry-After' => '2']), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    expect(TestClient::make($mock, $sleeper)->links()->get('x'))->toBeInstanceOf(LinkData::class)
        ->and($sleeper->waits)->toBe([2.0]);
    $mock->assertSentCount(2);
});

it('does not retry a read 5xx whose Retry-After is above the cap', function (): void {
    $mock = new MockClient([serverRetryOutage(['Retry-After' => '10']), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    expect(fn () => TestClient::make($mock, $sleeper)->links()->get('x'))->toThrow(ServerException::class)
        ->and($sleeper->waits)->toBe([]);
    $mock->assertSentCount(1);
});

it('retries a read 5xx whose Retry-After equals the cap', function (): void {
    $mock = new MockClient([serverRetryOutage(['Retry-After' => '3']), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    TestClient::make($mock, $sleeper)->links()->get('x');

    expect($sleeper->waits)->toBe([3.0]);
    $mock->assertSentCount(2);
});

it('keeps the short backoff for a read 5xx with no Retry-After', function (): void {
    $mock = new MockClient([serverRetryOutage(), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    TestClient::make($mock, $sleeper)->links()->get('x');

    expect($sleeper->waits)->toBe([0.5]);
});

it('ignores a RateLimit reset on a read 5xx', function (): void {
    $mock = new MockClient([serverRetryOutage(['RateLimit' => '"default";r=0;t=30']), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    TestClient::make($mock, $sleeper)->links()->get('x');

    expect($sleeper->waits)->toBe([0.5]);
    $mock->assertSentCount(2);
});

it('treats a past HTTP-date Retry-After on a read 5xx as no wait', function (): void {
    $mock = new MockClient([serverRetryOutage(['Retry-After' => 'Wed, 21 Oct 2015 07:28:00 GMT']), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    TestClient::make($mock, $sleeper)->links()->get('x');

    expect($sleeper->waits)->toBe([0.0]);
});

it('does not retry when Retry-After exceeds a lowered cap', function (): void {
    $mock = new MockClient([serverRetryOutage(['Retry-After' => '2']), serverRetryRecovery()]);
    $sleeper = new RecordingSleeper;

    expect(fn () => TestClient::make($mock, $sleeper)->withRetry(tries: 3, maxWait: 1.0)->links()->get('x'))
        ->toThrow(ServerException::class)
        ->and($sleeper->waits)->toBe([]);
    $mock->assertSentCount(1);
});

it('never re-sends a write after a 5xx, whatever Retry-After says', function (): void {
    $mock = new MockClient([serverRetryOutage(['Retry-After' => '1']), MockResponse::make(['data' => Fixtures::link()], 201)]);
    $sleeper = new RecordingSleeper;

    expect(fn () => TestClient::make($mock, $sleeper)->links()->create(destinationUrl: 'https://example.com/landing'))
        ->toThrow(ServerException::class)
        ->and($sleeper->waits)->toBe([]);
    $mock->assertSentCount(1);
});
