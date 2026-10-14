<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

function ok(): MockResponse
{
    return MockResponse::make(['data' => Fixtures::link(['slug' => 'x'])], 200);
}

/**
 * @return list<PendingRequest>
 */
function sentRequests(MockClient $mock): array
{
    return array_map(
        static fn ($response): PendingRequest => $response->getPendingRequest(),
        $mock->getRecordedResponses(),
    );
}

it('keeps the original key after a copy with another key is made and used', function (): void {
    $mock = new MockClient([ok(), ok(), ok()]);
    $a = TestClient::make($mock);

    $a->links()->get('x');
    $b = $a->withApiKey('tk_test_other_key');
    $b->links()->get('x');
    $a->links()->get('x');

    expect($b)->not->toBe($a)
        ->and(array_map(
            static fn (PendingRequest $p): ?string => $p->headers()->get('Authorization'),
            sentRequests($mock),
        ))->toBe([
            'Bearer '.TestClient::API_KEY,
            'Bearer tk_test_other_key',
            'Bearer '.TestClient::API_KEY,
        ]);
});

dataset('copies', [
    'base url' => [
        fn (TrackToClient $c): TrackToClient => $c->withBaseUrl('https://staging.track.to'),
        fn (PendingRequest $p): mixed => $p->getUri()->getHost(),
        'staging.track.to',
        'api.track.to',
    ],
    'version null' => [
        fn (TrackToClient $c): TrackToClient => $c->withVersion(null),
        fn (PendingRequest $p): mixed => $p->headers()->get('Track-Version'),
        null,
        '2026-07-06',
    ],
    'version value' => [
        fn (TrackToClient $c): TrackToClient => $c->withVersion('2027-01-01'),
        fn (PendingRequest $p): mixed => $p->headers()->get('Track-Version'),
        '2027-01-01',
        '2026-07-06',
    ],
    'user agent suffix' => [
        fn (TrackToClient $c): TrackToClient => $c->withUserAgentSuffix('acme-app/1.2'),
        fn (PendingRequest $p): bool => str_ends_with((string) $p->headers()->get('User-Agent'), ' acme-app/1.2'),
        true,
        false,
    ],
    'timeout' => [
        fn (TrackToClient $c): TrackToClient => $c->withTimeout(5.0),
        fn (PendingRequest $p): mixed => $p->config()->get('timeout'),
        5.0,
        30.0,
    ],
    'connect timeout' => [
        fn (TrackToClient $c): TrackToClient => $c->withConnectTimeout(2.0),
        fn (PendingRequest $p): mixed => $p->config()->get('connect_timeout'),
        2.0,
        10.0,
    ],
]);

it('changes the copy only, even after the original has sent a request', function (
    Closure $copy,
    Closure $observe,
    mixed $copyValue,
    mixed $originalValue,
): void {
    $mock = new MockClient([ok(), ok(), ok()]);
    $original = TestClient::make($mock);

    $original->links()->get('x');
    $copy($original)->links()->get('x');
    $original->links()->get('x');

    [, $fromCopy, $fromOriginalAgain] = sentRequests($mock);

    expect($observe($fromCopy))->toBe($copyValue)
        ->and($observe($fromOriginalAgain))->toBe($originalValue);
})->with('copies');

it('returns a mock-carrying copy and leaves the receiver without a mock', function (): void {
    $base = new TrackToClient(apiKey: TestClient::API_KEY);
    $mocked = $base->withMockClient(new MockClient([]));

    expect($mocked)->not->toBe($base)
        ->and($mocked->hasMockClient())->toBeTrue()
        ->and($base->hasMockClient())->toBeFalse();
});

it('sends the new key from withApiKey() even after authenticate() replaced the authenticator', function (): void {
    $mock = new MockClient([ok(), ok(), ok()]);
    $original = TestClient::make($mock);
    $original->authenticate(new TokenAuthenticator('old'));

    $copy = $original->withApiKey('new');
    $copy->links()->get('x');
    $original->links()->get('x');

    expect(array_map(
        static fn (PendingRequest $p): ?string => $p->headers()->get('Authorization'),
        sentRequests($mock),
    ))->toBe(['Bearer new', 'Bearer old']);
});
