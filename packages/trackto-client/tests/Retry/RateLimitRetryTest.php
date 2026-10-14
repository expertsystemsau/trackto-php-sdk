<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Exceptions\RateLimitedException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\RecordingSleeper;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function limited(array $headers): MockResponse
{
    return Fixtures::errorResponse('rate_limited', headers: $headers);
}

function linkOk(int $status = 200): MockResponse
{
    return MockResponse::make(['data' => Fixtures::link()], $status);
}

it('retries a 429 with Retry-After inside the cap', function (): void {
    $mock = new MockClient([limited(['Retry-After' => '1']), linkOk()]);
    $sleeper = new RecordingSleeper;

    expect(TestClient::make($mock, $sleeper)->links()->get('x'))->toBeInstanceOf(LinkData::class)
        ->and($sleeper->waits)->toBe([1.0]);
    $mock->assertSentCount(2);
});

it('throws at once when Retry-After is above the cap, exposing the wait', function (): void {
    $mock = new MockClient([limited(['Retry-After' => '10']), linkOk()]);
    $sleeper = new RecordingSleeper;

    try {
        TestClient::make($mock, $sleeper)->links()->get('x');
    } catch (RateLimitedException $e) {
        expect($e->rateLimit()->retryAfter)->toBe(10)->and($sleeper->waits)->toBe([]);
        $mock->assertSentCount(1);

        return;
    }

    $this->fail('Expected a RateLimitedException.');
});

it('does not retry a 429 with no timing', function (): void {
    $mock = new MockClient([limited([]), linkOk()]);
    $sleeper = new RecordingSleeper;

    expect(fn () => TestClient::make($mock, $sleeper)->links()->get('x'))->toThrow(RateLimitedException::class)
        ->and($sleeper->waits)->toBe([]);
    $mock->assertSentCount(1);
});

it('retries a 429 that only carries RateLimit reset timing', function (): void {
    $mock = new MockClient([limited(['RateLimit' => '"default";r=0;t=2']), linkOk()]);
    $sleeper = new RecordingSleeper;

    TestClient::make($mock, $sleeper)->links()->get('x');

    expect($sleeper->waits)->toBe([2.0]);
    $mock->assertSentCount(2);
});

it('parses an HTTP-date Retry-After end to end', function (): void {
    $date = gmdate('D, d M Y H:i:s \G\M\T', time() + 2);
    $mock = new MockClient([limited(['Retry-After' => $date]), linkOk()]);
    $sleeper = new RecordingSleeper;

    TestClient::make($mock, $sleeper)->links()->get('x');

    expect($sleeper->waits)->toHaveCount(1)
        ->and($sleeper->waits[0])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(3.0);
    $mock->assertSentCount(2);
});

it('treats the cap as inclusive', function (): void {
    $retried = new MockClient([limited(['Retry-After' => '3']), linkOk()]);
    TestClient::make($retried)->links()->get('x');
    $retried->assertSentCount(2);

    $refused = new MockClient([limited(['Retry-After' => '4']), linkOk()]);
    expect(fn () => TestClient::make($refused)->links()->get('x'))->toThrow(RateLimitedException::class);
    $refused->assertSentCount(1);
});

it('retries a POST after a timed 429, because the request was not processed', function (): void {
    $mock = new MockClient([limited(['Retry-After' => '1']), linkOk(201)]);

    expect(TestClient::make($mock)->links()->create(destinationUrl: 'https://example.com/landing'))->toBeInstanceOf(LinkData::class);
    $mock->assertSentCount(2);
});

it('stops after three attempts', function (): void {
    $mock = new MockClient([limited(['Retry-After' => '1']), limited(['Retry-After' => '1']), limited(['Retry-After' => '1']), linkOk()]);

    expect(fn () => TestClient::make($mock)->links()->get('x'))->toThrow(RateLimitedException::class);
    $mock->assertSentCount(3);
});

it('never sleeps longer than the cap', function (string $retryAfter): void {
    $mock = new MockClient([limited(['Retry-After' => $retryAfter]), linkOk()]);
    $sleeper = new RecordingSleeper;

    try {
        TestClient::make($mock, $sleeper)->links()->get('x');
    } catch (RateLimitedException) {
    }

    expect($sleeper->waits === [] ? 0.0 : max($sleeper->waits))->toBeLessThanOrEqual(3.0);
})->with([
    ...array_map(static fn (int $s): array => [(string) $s], range(0, 10)),
    'far-future date' => [gmdate('D, d M Y H:i:s \G\M\T', time() + 86400)],
]);
