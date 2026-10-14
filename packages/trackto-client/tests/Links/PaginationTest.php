<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Pagination\LinksPaginator;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
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

it('ends the walk on its own page count when the server repeats a stale current_page', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page(pageLinks(['a']), 1, 3, 1, 3), 200),
        MockResponse::make(Fixtures::page(pageLinks(['b']), 1, 3, 1, 3), 200),
        MockResponse::make(Fixtures::page(pageLinks(['c']), 1, 3, 1, 3), 200),
    ]);

    expect(slugsOf(TestClient::make($mock)->links()->all(perPage: 1)))->toBe(['a', 'b', 'c']);
    $mock->assertSentCount(3);
});

it('caps the walk at a hard page ceiling', function (): void {
    expect((new ReflectionProperty(LinksPaginator::class, 'maxPages'))->getDefaultValue())->toBe(10_000);
});

it('hydrates each page response once while items() walks three pages in order', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page(pageLinks(['a', 'b']), 1, 3, 2, 5), 200),
        MockResponse::make(Fixtures::page(pageLinks(['c', 'd']), 2, 3, 2, 5), 200),
        MockResponse::make(Fixtures::page(pageLinks(['e']), 3, 3, 2, 5), 200),
    ]);
    $paginator = new LinksPaginator(TestClient::make($mock), new ListLinksRequest(perPage: 2));

    expect(slugsOf($paginator->items()))->toBe(['a', 'b', 'c', 'd', 'e'])
        ->and($paginator->hydrations)->toBe(3);
});

it('hydrates each page once across a full links()->all() walk', function (): void {
    $mock = new MockClient([
        MockResponse::make(Fixtures::page(pageLinks(['a', 'b']), 1, 3, 2, 5), 200),
        MockResponse::make(Fixtures::page(pageLinks(['c', 'd']), 2, 3, 2, 5), 200),
        MockResponse::make(Fixtures::page(pageLinks(['e']), 3, 3, 2, 5), 200),
    ]);
    $links = TestClient::make($mock)->links()->all(perPage: 2);

    expect(slugsOf($links))->toBe(['a', 'b', 'c', 'd', 'e'])
        ->and($links->hydrations)->toBe(3);
});

it('walks identical page bodies to the reported last page without a non-SDK exception', function (): void {
    $same = static fn (): MockResponse => MockResponse::make(Fixtures::page(pageLinks(['a']), 1, 10, 1, 10), 200);
    $mock = new MockClient(array_map($same, range(1, 10)));

    expect(slugsOf(TestClient::make($mock)->links()->all(perPage: 1)))->toHaveCount(10);
    $mock->assertSentCount(10);
});
