<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Enums\LinkSort;
use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Exceptions\ValidationException;
use ExpertSystems\TrackTo\Resources\LinksResource;
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

it('lists with only the default sort when no filters are given', function (): void {
    $mock = new MockClient([MockResponse::make(Fixtures::page(), 200)]);

    TestClient::make($mock)->links()->list();

    expect($mock->getLastPendingRequest()?->query()->all())->toBe(['sort' => 'recent']);
});

it('maps every list argument to its query key', function (): void {
    $mock = new MockClient([MockResponse::make(Fixtures::page(), 200)]);

    TestClient::make($mock)->links()->list(
        search: 'acme', folderId: 3, tag: 7, sort: LinkSort::Clicks, perPage: 50, page: 2,
    );

    expect($mock->getLastPendingRequest()?->query()->all())->toBe([
        'q' => 'acme', 'folder' => 3, 'tag' => 7, 'sort' => 'clicks', 'per_page' => 50, 'page' => 2,
    ]);
});

it('returns a typed page in response order with its meta', function (): void {
    $links = [Fixtures::link(['id' => 1, 'slug' => 'a']), Fixtures::link(['id' => 2, 'slug' => 'b'])];
    $mock = new MockClient([MockResponse::make(Fixtures::page($links, 2, 4, 2, 7), 200)]);

    $page = TestClient::make($mock)->links()->list(perPage: 2, page: 2);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[0]->slug)->toBe('a')
        ->and($page->items[1]->slug)->toBe('b')
        ->and([$page->currentPage, $page->lastPage, $page->perPage, $page->total])->toBe([2, 4, 2, 7]);
});

it('returns an empty page for zero links after one request', function (): void {
    $mock = new MockClient([MockResponse::make(Fixtures::page(), 200)]);

    $page = TestClient::make($mock)->links()->list();

    expect($page->items)->toBe([])
        ->and($page->total)->toBe(0);
    $mock->assertSentCount(1);
});

it('raises UnexpectedResponseException when a page body has no meta', function (): void {
    $body = Fixtures::page([Fixtures::link()]);
    unset($body['meta']);
    $mock = new MockClient([MockResponse::make($body, 200)]);

    expect(fn () => TestClient::make($mock)->links()->list())->toThrow(UnexpectedResponseException::class);
});

it('creates with only the destination url when nothing else is given', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link(['slug' => 'new'])], 201)]);

    $link = TestClient::make($mock)->links()->create(destinationUrl: 'https://example.com/a');

    $pending = $mock->getLastPendingRequest();

    expect($link->slug)->toBe('new')
        ->and($pending?->getMethod()->value)->toBe('POST')
        ->and($pending?->getUri()->getPath())->toBe('/links')
        ->and($pending?->body()->all())->toBe(['destination_url' => 'https://example.com/a'])
        ->and($pending?->headers()->get('Content-Type'))->toBe('application/json');
});

it('sends every create argument, formatting expiry as ISO 8601 with its offset', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 201)]);

    TestClient::make($mock)->links()->create(
        destinationUrl: 'https://example.com/a',
        slug: 'spring',
        expiresAt: new DateTimeImmutable('2026-12-01 09:30:00', new DateTimeZone('Australia/Brisbane')),
        password: 'hunter22',
        folderId: 3,
        tags: ['launch', 'q4'],
    );

    expect($mock->getLastPendingRequest()?->body()->all())->toBe([
        'destination_url' => 'https://example.com/a',
        'slug' => 'spring',
        'expires_at' => '2026-12-01T09:30:00+10:00',
        'password' => 'hunter22',
        'folder_id' => 3,
        'tags' => ['launch', 'q4'],
    ]);
});

it('throws ValidationException with field errors on a 422 create', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('validation_error', ['destination_url' => ['The destination url field is required.']])]);

    try {
        TestClient::make($mock)->links()->create(destinationUrl: '');
    } catch (ValidationException $e) {
        expect($e->errorsFor('destination_url'))->toBe(['The destination url field is required.']);

        return;
    }

    throw new RuntimeException('Expected ValidationException.');
});

it('pins the public parameter names of create() and list()', function (): void {
    $names = static fn (string $method): array => array_map(
        static fn (ReflectionParameter $p): string => $p->getName(),
        (new ReflectionMethod(LinksResource::class, $method))->getParameters(),
    );

    expect($names('create'))->toBe(['destinationUrl', 'slug', 'expiresAt', 'password', 'folderId', 'tags'])
        ->and($names('list'))->toBe(['search', 'folderId', 'tag', 'sort', 'perPage', 'page']);
});

it('raises a ConfigurationException, not a JsonException, for a body field that is not valid UTF-8', function (): void {
    $client = TestClient::make(new MockClient([MockResponse::make(['data' => Fixtures::link()], 201)]));

    try {
        $client->links()->create(destinationUrl: "https://example.com/\xB1\xB1");
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(ConfigurationException::class)
            ->and($e->getMessage())->not->toContain('example.com')
            ->and($e->getPrevious())->toBeNull();

        return;
    }

    $this->fail('No exception thrown.');
});

it('drops tag keys on create so the body tags are always a JSON list', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    TestClient::make($mock)->links()->create('https://example.com/a', tags: ['x' => 'a', 'y' => 'b']);

    expect($mock->getLastPendingRequest()?->body()?->all())->toMatchArray(['tags' => ['a', 'b']]);
});
