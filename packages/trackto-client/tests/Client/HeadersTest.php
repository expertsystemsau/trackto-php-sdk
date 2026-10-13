<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\FolderData;
use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\TagData;
use ExpertSystems\TrackTo\Enums\LinkStatus;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

dataset('operations', [
    'get' => [fn (TrackToClient $client): mixed => $client->links()->get('007')],
]);

it('sends the contract headers and hydrates a LinkData', function (Closure $operation): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link(['slug' => '007'])], 200)]);

    $link = $operation(TestClient::make($mock));

    expect($link)->toBeInstanceOf(LinkData::class)
        ->and($link->slug)->toBe('007')
        ->and($link->status)->toBe(LinkStatus::Active)
        ->and($link->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($link->folder)->toBeInstanceOf(FolderData::class)
        ->and($link->tags[0])->toBeInstanceOf(TagData::class);

    $pending = $mock->getLastPendingRequest();

    expect($pending?->getMethod()->value)->toBe('GET')
        ->and((string) $pending?->getUri())->toBe('https://api.track.to/links/007')
        ->and($pending?->headers()->get('Authorization'))->toBe('Bearer '.TestClient::API_KEY)
        ->and($pending?->headers()->get('User-Agent'))->toMatch('#^trackto-php/\S+ php/\d+\.\d+\.\d+$#')
        ->and($pending?->headers()->get('Track-Version'))->toBe('2026-07-06')
        ->and($pending?->headers()->get('Accept'))->toBe('application/json');
})->with('operations');
