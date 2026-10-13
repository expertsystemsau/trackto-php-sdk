<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\NotFoundException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Resources\TagsResource;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('lists tags in API order from one GET with no page parameter', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => [
        Fixtures::tag(['id' => 2, 'name' => 'beta']),
        Fixtures::tag(['id' => 1, 'name' => 'alpha']),
    ]], 200)]);

    $tags = TestClient::make($mock)->tags()->list();
    $pending = $mock->getLastPendingRequest();

    expect(array_map(static fn ($tag): string => $tag->name, $tags))->toBe(['beta', 'alpha'])
        ->and($pending?->getMethod()->value)->toBe('GET')
        ->and($pending?->getUri()->getPath())->toBe('/tags')
        ->and($pending?->query()->all())->toBe([]);
    $mock->assertSentCount(1);
});

it('returns an empty list after one request', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => []], 200)]);

    expect(TestClient::make($mock)->tags()->list())->toBe([]);
    $mock->assertSentCount(1);
});

it('raises UnexpectedResponseException when the list body is not a list of objects', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => ['x']], 200)]);

    expect(fn () => TestClient::make($mock)->tags()->list())->toThrow(UnexpectedResponseException::class);
});

it('sends no color key when the color is null and returns the created tag', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::tag(['links_count' => 0])], 201)]);

    $tag = TestClient::make($mock)->tags()->create(name: 'launch');
    $pending = $mock->getLastPendingRequest();

    expect($tag->linksCount)->toBe(0)
        ->and($pending?->getMethod()->value)->toBe('POST')
        ->and($pending?->getUri()->getPath())->toBe('/tags')
        ->and($pending?->body()?->all())->toBe(['name' => 'launch']);
    $mock->assertSentCount(1);
});

it('sends the color when given', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::tag(['color' => '#ff0000'])], 201)]);

    $tag = TestClient::make($mock)->tags()->create(name: 'launch', color: '#ff0000');

    expect($tag->color)->toBe('#ff0000')
        ->and($mock->getLastPendingRequest()?->body()?->all())->toBe(['name' => 'launch', 'color' => '#ff0000']);
});

it('round-trips a unicode name unchanged', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::tag(['name' => 'Été 🚀'])], 201)]);

    $tag = TestClient::make($mock)->tags()->create(name: 'Été 🚀');

    expect($tag->name)->toBe('Été 🚀')
        ->and($mock->getLastPendingRequest()?->body()?->all())->toBe(['name' => 'Été 🚀']);
});

it('sends one DELETE to the integer id and returns nothing on 204', function (): void {
    $mock = new MockClient([MockResponse::make('', 204)]);

    $result = TestClient::make($mock)->tags()->delete(7);
    $pending = $mock->getLastPendingRequest();

    expect($result)->toBeNull()
        ->and($pending?->getMethod()->value)->toBe('DELETE')
        ->and($pending?->getUri()->getPath())->toBe('/tags/7');
    $mock->assertSentCount(1);
});

it('raises NotFoundException every time, and sends id 0 as given', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('not_found'), Fixtures::errorResponse('not_found')]);
    $client = TestClient::make($mock);

    expect(fn () => $client->tags()->delete(0))->toThrow(NotFoundException::class)
        ->and($mock->getLastPendingRequest()?->getUri()->getPath())->toBe('/tags/0')
        ->and(fn () => $client->tags()->delete(0))->toThrow(NotFoundException::class);
    $mock->assertSentCount(2);
});

it('keeps a folder and a tag with the same id on independent paths', function (): void {
    $mock = new MockClient([MockResponse::make('', 204), MockResponse::make('', 204)]);
    $client = TestClient::make($mock);

    $client->folders()->delete(7);
    $client->tags()->delete(7);

    $paths = array_map(
        static fn ($response): string => $response->getPendingRequest()->getUri()->getPath(),
        $mock->getRecordedResponses(),
    );

    expect($paths)->toBe(['/folders/7', '/tags/7']);
});

it('sends a create or delete that gets a 500 exactly once, with the default retry on', function (Closure $call): void {
    $mock = new MockClient([Fixtures::errorResponse('server_error'), MockResponse::make(['data' => Fixtures::tag()], 201)]);

    expect(fn () => $call(TestClient::make($mock)->tags()))->toThrow(ServerException::class);
    $mock->assertSentCount(1);
})->with([
    'create' => [fn (TagsResource $tags): mixed => $tags->create('launch')],
    'delete' => [fn (TagsResource $tags): mixed => $tags->delete(1)],
]);

it('returns a fresh resource on every call', function (): void {
    $client = TestClient::make(new MockClient([]));

    expect($client->tags())->toBeInstanceOf(TagsResource::class)
        ->and($client->tags())->not->toBe($client->tags());
});
