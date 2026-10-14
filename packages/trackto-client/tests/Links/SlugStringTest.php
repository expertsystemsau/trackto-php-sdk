<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * Runs one call against a mock and returns what it sent and received.
 *
 * @param  callable(LinksResource): mixed  $call
 * @return array{path: string, body: string, result: mixed}
 */
function slugStringSend(callable $call, MockResponse $response): array
{
    $mock = new MockClient([$response]);
    $result = $call(TestClient::make($mock)->links());
    $pending = $mock->getLastPendingRequest();

    return ['path' => $pending?->getUri()->getPath() ?? '', 'body' => (string) $pending?->body(), 'result' => $result];
}

it('keeps slug "007" a string through update', function (): void {
    $sent = slugStringSend(
        fn (LinksResource $links) => $links->update(slug: '007', destinationUrl: 'https://example.com/a', newSlug: '007'),
        MockResponse::make(['data' => Fixtures::link(['slug' => '007'])], 200),
    );

    expect($sent['path'])->toBe('/links/007')
        ->and($sent['body'])->toContain('"slug":"007"')
        ->and($sent['result']->slug)->toBeString()->toBe('007');
});

it('keeps slug "007" a string through delete', function (): void {
    $sent = slugStringSend(fn (LinksResource $links) => $links->delete('007'), MockResponse::make('', 204));

    expect($sent['path'])->toBe('/links/007');
});

it('keeps slug "007" a string through analytics', function (): void {
    $sent = slugStringSend(
        fn (LinksResource $links) => $links->analytics('007'),
        MockResponse::make(Fixtures::analytics(['link' => Fixtures::link(['slug' => '007'])]), 200),
    );

    expect($sent['path'])->toBe('/links/007/analytics')
        ->and($sent['result']->link->slug)->toBeString()->toBe('007');
});

it('keeps slug "007" a string through every bulk action', function (callable $call): void {
    $sent = slugStringSend($call, MockResponse::make(Fixtures::bulk(['slugs' => ['007']]), 200));

    expect($sent['body'])->toContain('"slugs":["007"]')
        ->and($sent['result']->slugs)->toBe(['007']);
})->with([
    'move' => [fn (LinksResource $links) => $links->bulkMove(slugs: ['007'], folderId: null)],
    'tag' => [fn (LinksResource $links) => $links->bulkTag(slugs: ['007'], tags: ['x'])],
    'delete' => [fn (LinksResource $links) => $links->bulkDelete(slugs: ['007'])],
]);
