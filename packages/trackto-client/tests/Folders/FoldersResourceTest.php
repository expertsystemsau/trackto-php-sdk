<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\NotFoundException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Exceptions\ValidationException;
use ExpertSystems\TrackTo\Resources\FoldersResource;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('lists folders in API order from one GET with no page parameter', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => [
        Fixtures::folder(['id' => 2, 'name' => 'Beta']),
        Fixtures::folder(['id' => 1, 'name' => 'Alpha']),
    ]], 200)]);

    $folders = TestClient::make($mock)->folders()->list();
    $pending = $mock->getLastPendingRequest();

    expect(array_map(static fn ($folder): string => $folder->name, $folders))->toBe(['Beta', 'Alpha'])
        ->and($pending?->getMethod()->value)->toBe('GET')
        ->and($pending?->getUri()->getPath())->toBe('/folders')
        ->and($pending?->query()->all())->toBe([]);
    $mock->assertSentCount(1);
});

it('returns an empty list after one request', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => []], 200)]);

    expect(TestClient::make($mock)->folders()->list())->toBe([]);
    $mock->assertSentCount(1);
});

it('raises UnexpectedResponseException when the list body is not a list of objects', function (array $body): void {
    $mock = new MockClient([MockResponse::make($body, 200)]);

    expect(fn () => TestClient::make($mock)->folders()->list())->toThrow(UnexpectedResponseException::class);
})->with([
    'data is an object' => [['data' => ['id' => 1, 'name' => 'A']]],
    'string item' => [['data' => ['x']]],
    'no data' => [[]],
]);

it('sends the name alone and returns the created folder with its link count', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::folder(['links_count' => 0])], 201)]);

    $folder = TestClient::make($mock)->folders()->create(name: 'Campaigns');
    $pending = $mock->getLastPendingRequest();

    expect($folder->linksCount)->toBe(0)
        ->and($pending?->getMethod()->value)->toBe('POST')
        ->and($pending?->getUri()->getPath())->toBe('/folders')
        ->and($pending?->body()?->all())->toBe(['name' => 'Campaigns']);
    $mock->assertSentCount(1);
});

it('round-trips a unicode name unchanged', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::folder(['name' => 'Été 🚀'])], 201)]);

    $folder = TestClient::make($mock)->folders()->create(name: 'Été 🚀');

    expect($folder->name)->toBe('Été 🚀')
        ->and($mock->getLastPendingRequest()?->body()?->all())->toBe(['name' => 'Été 🚀']);
});

it('sends one DELETE to the integer id and returns nothing on 204', function (): void {
    $mock = new MockClient([MockResponse::make('', 204)]);

    $result = TestClient::make($mock)->folders()->delete(12);
    $pending = $mock->getLastPendingRequest();

    expect($result)->toBeNull()
        ->and($pending?->getMethod()->value)->toBe('DELETE')
        ->and($pending?->getUri()->getPath())->toBe('/folders/12');
    $mock->assertSentCount(1);
});

it('raises ValidationException when the folder still holds links', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('validation_error', ['folder' => ['The folder still has links.']])]);

    try {
        TestClient::make($mock)->folders()->delete(12);
    } catch (ValidationException $e) {
        expect($e->errorsFor('folder'))->toBe(['The folder still has links.']);

        return;
    }

    throw new RuntimeException('Expected ValidationException.');
});

it('raises NotFoundException every time, so a repeat delete is never swallowed', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('not_found'), Fixtures::errorResponse('not_found')]);
    $client = TestClient::make($mock);

    expect(fn () => $client->folders()->delete(9))->toThrow(NotFoundException::class)
        ->and(fn () => $client->folders()->delete(9))->toThrow(NotFoundException::class);
    $mock->assertSentCount(2);
});

it('sends boundary ids as given and surfaces the 404', function (int $id, string $path): void {
    $mock = new MockClient([Fixtures::errorResponse('not_found')]);

    expect(fn () => TestClient::make($mock)->folders()->delete($id))->toThrow(NotFoundException::class)
        ->and($mock->getLastPendingRequest()?->getUri()->getPath())->toBe($path);
})->with([
    'zero' => [0, '/folders/0'],
    'negative' => [-1, '/folders/-1'],
]);

it('sends a create or delete that gets a 500 exactly once, with the default retry on', function (Closure $call): void {
    $mock = new MockClient([Fixtures::errorResponse('server_error'), MockResponse::make(['data' => Fixtures::folder()], 201)]);

    expect(fn () => $call(TestClient::make($mock)->folders()))->toThrow(ServerException::class);
    $mock->assertSentCount(1);
})->with([
    'create' => [fn (FoldersResource $folders): mixed => $folders->create('Campaigns')],
    'delete' => [fn (FoldersResource $folders): mixed => $folders->delete(1)],
]);

it('returns a fresh resource on every call', function (): void {
    $client = TestClient::make(new MockClient([]));

    expect($client->folders())->toBeInstanceOf(FoldersResource::class)
        ->and($client->folders())->not->toBe($client->folders());
});
