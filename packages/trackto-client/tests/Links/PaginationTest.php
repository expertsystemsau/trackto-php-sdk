<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * @param  list<string>  $slugs
 * @return list<array<string, mixed>>
 */
function pageLinks(array $slugs): array
{
    return array_map(static fn (string $slug): array => Fixtures::link(['slug' => $slug]), $slugs);
}

/**
 * @return list<string>
 */
function slugsOf(iterable $links): array
{
    return array_map(static fn (LinkData $link): string => $link->slug, iterator_to_array($links, false));
}

it('walks every page in API order, building ?page=N itself', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page(pageLinks(['a', 'b']), 1, 3, 2, 5), 200),
        MockResponse::make(Fixtures::page(pageLinks(['c', 'd']), 2, 3, 2, 5), 200),
        MockResponse::make(Fixtures::page(pageLinks(['e']), 3, 3, 2, 5), 200),
    ]);

    $links = TestClient::make($mock)->links()->all(search: 'x', perPage: 2);

    expect(slugsOf($links))->toBe(['a', 'b', 'c', 'd', 'e']);
    $mock->assertSentCount(3);

    foreach ($mock->getRecordedResponses() as $i => $response) {
        $query = $response->getPendingRequest()->query()->all();

        expect($response->getPendingRequest()->getUri()->getPath())->toBe('/links')
            ->and($query)->toMatchArray(['page' => $i + 1, 'per_page' => 2, 'q' => 'x', 'sort' => 'recent']);
    }
});

it('stops after an exact multiple of perPage without an extra request', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page(pageLinks(['a', 'b']), 1, 2, 2, 4), 200),
        MockResponse::make(Fixtures::page(pageLinks(['c', 'd']), 2, 2, 2, 4), 200),
    ]);

    expect(slugsOf(TestClient::make($mock)->links()->all(perPage: 2)))->toBe(['a', 'b', 'c', 'd']);
    $mock->assertSentCount(2);
});

it('stops on an empty last page without a further request', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page(pageLinks(['a', 'b']), 1, 3, 2, 4), 200),
        MockResponse::make(Fixtures::page(pageLinks(['c', 'd']), 2, 3, 2, 4), 200),
        MockResponse::make(Fixtures::page([], 3, 3, 2, 4), 200),
    ]);

    expect(slugsOf(TestClient::make($mock)->links()->all(perPage: 2)))->toBe(['a', 'b', 'c', 'd']);
    $mock->assertSentCount(3);
});

it('yields nothing after one request when there are no links', function (): void {
    $mock = new MockClient([MockResponse::make(Fixtures::page(), 200)]);

    expect(slugsOf(TestClient::make($mock)->links()->all()))->toBe([]);
    $mock->assertSentCount(1);
});

it('sends a fresh set of requests each time the result is iterated', function (): void {
    $page = static fn (string $slug): MockResponse => MockResponse::make(Fixtures::page(pageLinks([$slug]), 1, 1, 25, 1), 200);
    $mock = new MockClient([$page('a'), $page('a')]);

    $links = TestClient::make($mock)->links()->all();

    expect(slugsOf($links))->toBe(['a'])
        ->and(slugsOf($links))->toBe(['a']);
    $mock->assertSentCount(2);
});

it('never requests a URL taken from a response', function (): void {
    $first = Fixtures::page(pageLinks(['a']), 1, 2, 1, 2);
    $first['links']['next'] = 'https://evil.example/links?page=2';
    $mock = new MockClient([
        MockResponse::make($first, 200),
        MockResponse::make(Fixtures::page(pageLinks(['b']), 2, 2, 1, 2), 200),
    ]);

    expect(slugsOf(TestClient::make($mock)->links()->all(perPage: 1)))->toBe(['a', 'b']);

    foreach ($mock->getRecordedResponses() as $i => $response) {
        $uri = $response->getPendingRequest()->getUri();

        expect($uri->getHost())->toBe('api.track.to')
            ->and($uri->getPath())->toBe('/links')
            ->and($response->getPendingRequest()->query()->get('page'))->toBe($i + 1);
    }
});
