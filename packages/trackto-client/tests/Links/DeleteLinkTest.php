<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;
use ExpertSystems\TrackTo\Exceptions\NotFoundException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('sends one DELETE and returns nothing on 204', function (): void {
    $mock = new MockClient([MockResponse::make('', 204)]);

    $result = TestClient::make($mock)->links()->delete('abc123');
    $pending = $mock->getLastPendingRequest();

    expect($result)->toBeNull()
        ->and($pending?->getMethod()->value)->toBe('DELETE')
        ->and($pending?->getUri()->getPath())->toBe('/links/abc123');
    $mock->assertSentCount(1);
});

it('keeps the slug a single encoded path segment', function (string $slug, string $path): void {
    $mock = new MockClient([MockResponse::make('', 204)]);

    TestClient::make($mock)->links()->delete($slug);

    expect($mock->getLastPendingRequest()?->getUri()->getPath())->toBe($path);
})->with([
    'numeric-looking' => ['007', '/links/007'],
    'inner dot' => ['v1.2', '/links/v1%2E2'],
    'dot segments' => ['..', '/links/%2E%2E'],
]);

it('rejects a blank slug before sending anything', function (string $slug): void {
    $mock = new MockClient([MockResponse::make('', 204)]);

    expect(fn () => TestClient::make($mock)->links()->delete($slug))->toThrow(InvalidArgumentException::class);
    $mock->assertNothingSent();
})->with(['empty' => [''], 'spaces' => ['  ']]);

it('raises NotFoundException every time, so a repeat delete is never swallowed', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('not_found'), Fixtures::errorResponse('not_found')]);
    $client = TestClient::make($mock);

    expect(fn () => $client->links()->delete('gone'))->toThrow(NotFoundException::class)
        ->and(fn () => $client->links()->delete('gone'))->toThrow(NotFoundException::class);
    $mock->assertSentCount(2);
});

it('sends a delete that gets a 500 exactly once, with the default retry on', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('server_error'), MockResponse::make('', 204)]);

    expect(fn () => TestClient::make($mock)->links()->delete('abc123'))->toThrow(ServerException::class);
    $mock->assertSentCount(1);
});

it('raises NotFoundException for analytics on a link that was just deleted', function (): void {
    $mock = new MockClient([MockResponse::make('', 204), Fixtures::errorResponse('not_found')]);
    $client = TestClient::make($mock);

    $client->links()->delete('gone');

    expect(fn () => $client->links()->analytics('gone'))->toThrow(NotFoundException::class);
    $mock->assertSentCount(2);
});
