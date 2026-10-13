<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\BulkResultData;
use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\RateLimitedException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\Mocks;
use ExpertSystems\TrackTo\Tests\Support\RecordingSleeper;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * The three failures a retrying client would re-send on: the mock for each, the exception, and how many sends it counts.
 *
 * @return array<string, array{string, class-string<Throwable>}>
 */
function bulkAttemptFailures(): array
{
    return [
        '500' => ['server', ServerException::class],
        'dropped connection' => ['drop', ConnectionException::class],
        'timed 429' => ['rate', RateLimitedException::class],
    ];
}

/**
 * Runs one failing bulk call under withRetry(tries: 5) and returns [requests sent, recorded waits].
 *
 * @param  Closure(TrackToClient): BulkResultData  $call
 * @param  class-string<Throwable>  $exception
 * @return array{int, list<float>}
 */
function bulkAttemptRun(Closure $call, string $kind, string $exception): array
{
    $attempts = 0;
    $sleeper = new RecordingSleeper;
    $success = MockResponse::make(Fixtures::bulk(), 200);
    $failure = match ($kind) {
        'server' => Fixtures::errorResponse('server_error'),
        'rate' => Fixtures::errorResponse('rate_limited', headers: ['Retry-After' => '1']),
        default => Mocks::drop($attempts),
    };
    // Spare responses after the failure: a retry would consume one and the count would show it.
    $mock = new MockClient([$failure, $success, $success, $success, $success]);
    $client = TestClient::make($mock, $sleeper)->withRetry(tries: 5);

    expect(fn () => $call($client))->toThrow($exception);

    return [$kind === 'drop' ? $attempts : count($mock->getRecordedResponses()), $sleeper->waits];
}

it('sends a bulk call once and never sleeps, under withRetry(5)', function (Closure $call, string $kind, string $exception): void {
    [$sent, $waits] = bulkAttemptRun($call, $kind, $exception);

    expect($sent)->toBe(1)->and($waits)->toBe([]);
})->with([
    'move' => [fn (TrackToClient $client): BulkResultData => $client->links()->bulkMove(['a'], 1)],
    'tag' => [fn (TrackToClient $client): BulkResultData => $client->links()->bulkTag(['a'], ['x'])],
    'delete' => [fn (TrackToClient $client): BulkResultData => $client->links()->bulkDelete(['a'])],
])->with(bulkAttemptFailures());

it('does retry a timed 429 on a non-bulk POST, so the bulk case can fail', function (): void {
    $sleeper = new RecordingSleeper;
    $mock = new MockClient([
        Fixtures::errorResponse('rate_limited', headers: ['Retry-After' => '1']),
        MockResponse::make(['data' => Fixtures::link()], 201),
    ]);

    TestClient::make($mock, $sleeper)->withRetry(tries: 5)->links()->create(destinationUrl: 'https://example.com/landing');

    $mock->assertSentCount(2);
    expect($sleeper->waits)->toBe([1.0]);
});
