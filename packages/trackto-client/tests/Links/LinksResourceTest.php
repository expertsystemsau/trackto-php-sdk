<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('keeps a numeric-looking slug as a string', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link(['slug' => '007'])], 200)]);

    $link = TestClient::make($mock)->links()->get('007');

    expect($link->slug)->toBeString()->toBe('007')
        ->and($mock->getLastPendingRequest()?->getUri()->getPath())->toBe('/links/007');
});

it('percent-encodes the slug into a single path segment', function (string $slug, string $path): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    TestClient::make($mock)->links()->get($slug);

    expect($mock->getLastPendingRequest()?->getUri()->getPath())->toBe($path);
})->with([
    'space and slash' => ['a b/c', '/links/a%20b%2Fc'],
    'unicode' => ['é', '/links/%C3%A9'],
    'query and fragment' => ['x?y#z', '/links/x%3Fy%23z'],
    'percent' => ['%41', '/links/%2541'],
    'dot segments' => ['..', '/links/%2E%2E'],
    'inner dot' => ['v1.2', '/links/v1%2E2'],
]);

it('rejects a blank slug before sending anything', function (string $slug): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    expect(fn () => TestClient::make($mock)->links()->get($slug))
        ->toThrow(InvalidArgumentException::class);

    $mock->assertNothingSent();
})->with(['empty' => [''], 'spaces' => ['   ']]);
