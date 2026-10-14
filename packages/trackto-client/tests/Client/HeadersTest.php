<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\FolderData;
use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\LinkPage;
use ExpertSystems\TrackTo\Data\TagData;
use ExpertSystems\TrackTo\Enums\LinkStatus;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

dataset('operations', [
    'get' => [fn (TrackToClient $client): mixed => $client->links()->get('007'), 'GET', '/links/007', 200],
    'list' => [fn (TrackToClient $client): mixed => $client->links()->list(), 'GET', '/links', 200],
    'create' => [fn (TrackToClient $client): mixed => $client->links()->create(destinationUrl: 'https://example.com/a'), 'POST', '/links', 201],
]);

it('sends the contract headers on every operation', function (Closure $operation, string $method, string $path, int $status): void {
    $body = $method === 'GET' && $path === '/links'
        ? Fixtures::page([Fixtures::link(['slug' => '007'])])
        : ['data' => Fixtures::link(['slug' => '007'])];
    $mock = new MockClient([MockResponse::make($body, $status)]);

    $result = $operation(TestClient::make($mock));

    $link = $result instanceof LinkPage ? $result->items[0] : $result;

    expect($link)->toBeInstanceOf(LinkData::class)
        ->and($link->slug)->toBe('007')
        ->and($link->status)->toBe(LinkStatus::Active)
        ->and($link->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($link->folder)->toBeInstanceOf(FolderData::class)
        ->and($link->tags[0])->toBeInstanceOf(TagData::class);

    $pending = $mock->getLastPendingRequest();

    expect($pending?->getMethod()->value)->toBe($method)
        ->and($pending?->getUri()->getHost())->toBe('api.track.to')
        ->and($pending?->getUri()->getPath())->toBe($path)
        ->and($pending?->headers()->get('Authorization'))->toBe('Bearer '.TestClient::API_KEY)
        ->and($pending?->headers()->get('User-Agent'))->toMatch('#^trackto-php/\S+ php/\d+\.\d+\.\d+$#')
        ->and($pending?->headers()->get('Track-Version'))->toBe('2026-07-06')
        ->and($pending?->headers()->get('Accept'))->toBe('application/json');
})->with('operations');
