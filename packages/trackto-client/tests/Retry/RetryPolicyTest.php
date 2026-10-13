<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\ForbiddenException;
use ExpertSystems\TrackTo\Exceptions\RateLimitedException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Exceptions\ValidationException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\RecordingSleeper;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use GuzzleHttp\Exception\ConnectException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

const DESTINATION = 'https://example.com/landing';

function policyServerError(): MockResponse
{
    return Fixtures::errorResponse('server_error');
}

function policyOk(int $status = 200): MockResponse
{
    return MockResponse::make(['data' => Fixtures::link()], $status);
}

/**
 * A connection failure mock that counts how often it is attempted (MockClient does not record thrown mocks).
 */
function policyDrop(int &$attempts): MockResponse
{
    return MockResponse::make()->throw(static function (PendingRequest $pending) use (&$attempts): FatalRequestException {
        $attempts++;

        return new FatalRequestException(new ConnectException('cURL error 7: Failed to connect', $pending->createPsrRequest()), $pending);
    });
}

it('never retries other 4xx responses, for reads or writes', function (string $name, string $class): void {
    $get = new MockClient([Fixtures::errorResponse($name), policyOk()]);
    expect(fn () => TestClient::make($get)->links()->get('x'))->toThrow($class);
    $get->assertSentCount(1);

    $post = new MockClient([Fixtures::errorResponse($name), policyOk(201)]);
    expect(fn () => TestClient::make($post)->links()->create(destinationUrl: DESTINATION))->toThrow($class);
    $post->assertSentCount(1);
})->with([
    ['forbidden', ForbiddenException::class],
    ['validation_error', ValidationException::class],
]);

it('retries a GET 5xx with a 0.5s backoff', function (): void {
    $mock = new MockClient([policyServerError(), policyServerError(), policyOk()]);
    $sleeper = new RecordingSleeper;

    expect(TestClient::make($mock, $sleeper)->links()->get('x'))->toBeInstanceOf(LinkData::class)
        ->and($sleeper->waits)->toBe([0.5, 0.5]);
    $mock->assertSentCount(3);
});

it('gives up on a GET 5xx after three attempts', function (): void {
    $mock = new MockClient([policyServerError(), policyServerError(), policyServerError(), policyOk()]);

    expect(fn () => TestClient::make($mock)->links()->get('x'))->toThrow(ServerException::class);
    $mock->assertSentCount(3);
});

it('never re-sends a POST after a 5xx', function (): void {
    $mock = new MockClient([policyServerError(), policyOk(201)]);
    $sleeper = new RecordingSleeper;

    expect(fn () => TestClient::make($mock, $sleeper)->links()->create(destinationUrl: DESTINATION))->toThrow(ServerException::class)
        ->and($sleeper->waits)->toBe([]);
    $mock->assertSentCount(1);
});

it('retries a GET connection failure but not a POST one', function (): void {
    $attempts = 0;
    $sleeper = new RecordingSleeper;
    $mock = new MockClient([policyDrop($attempts), policyDrop($attempts), policyOk()]);

    TestClient::make($mock, $sleeper)->links()->get('x');
    expect($attempts)->toBe(2)->and($sleeper->waits)->toBe([0.5, 0.5]);

    $attempts = 0;
    $mock = new MockClient([policyDrop($attempts), policyDrop($attempts), policyDrop($attempts), policyOk()]);
    expect(fn () => TestClient::make($mock)->links()->get('x'))->toThrow(ConnectionException::class);
    expect($attempts)->toBe(3);

    $attempts = 0;
    $sleeper = new RecordingSleeper;
    $mock = new MockClient([policyDrop($attempts), policyOk(201)]);
    expect(fn () => TestClient::make($mock, $sleeper)->links()->create(destinationUrl: DESTINATION))->toThrow(ConnectionException::class);
    expect($attempts)->toBe(1)->and($sleeper->waits)->toBe([]);
});

it('lets withRetry raise the attempt count', function (): void {
    $mock = new MockClient([...array_fill(0, 4, policyServerError()), policyOk()]);

    TestClient::make($mock)->withRetry(tries: 5)->links()->get('x');
    $mock->assertSentCount(5);
});

it('lets withoutRetry make a single attempt while still throwing typed exceptions', function (): void {
    $mock = new MockClient([policyServerError(), policyOk()]);
    expect(fn () => TestClient::make($mock)->withoutRetry()->links()->get('x'))->toThrow(ServerException::class);
    $mock->assertSentCount(1);

    $mock = new MockClient([Fixtures::errorResponse('rate_limited', headers: ['Retry-After' => '1']), policyOk()]);
    expect(fn () => TestClient::make($mock)->withoutRetry()->links()->get('x'))->toThrow(RateLimitedException::class);
    $mock->assertSentCount(1);
});

it('lets withRetry lower the cap, bounding backoff and refusing slower 429 waits', function (): void {
    $sleeper = new RecordingSleeper;
    $mock = new MockClient([policyServerError(), policyOk()]);
    TestClient::make($mock, $sleeper)->withRetry(tries: 3, maxWait: 0.2)->links()->get('x');
    expect($sleeper->waits)->toBe([0.2]);

    $mock = new MockClient([Fixtures::errorResponse('rate_limited', headers: ['Retry-After' => '1']), policyOk()]);
    expect(fn () => TestClient::make($mock)->withRetry(tries: 3, maxWait: 0.2)->links()->get('x'))->toThrow(RateLimitedException::class);
    $mock->assertSentCount(1);
});

it('leaves the original client retrying after a copy disables it', function (): void {
    $client = TestClient::make(new MockClient([policyServerError(), policyOk()]));
    $client->withoutRetry();

    expect($client->links()->get('x'))->toBeInstanceOf(LinkData::class);
});

it('rejects retry settings that cannot work', function (int $tries, float $maxWait): void {
    TestClient::make(new MockClient([]))->withRetry($tries, $maxWait);
})->with([
    'no attempts' => [0, 3.0],
    'negative cap' => [3, -1.0],
])->throws(ConfigurationException::class);

it('retries a failing page inside a lazy walk', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page([Fixtures::link(['slug' => 'a'])], 1, 2, 1, 2), 200),
        policyServerError(),
        MockResponse::make(Fixtures::page([Fixtures::link(['slug' => 'b'])], 2, 2, 1, 2), 200),
    ]);

    $slugs = array_map(static fn (LinkData $l): string => $l->slug, iterator_to_array(TestClient::make($mock)->links()->all(perPage: 1), false));

    expect($slugs)->toBe(['a', 'b']);
    $mock->assertSentCount(3);
});
