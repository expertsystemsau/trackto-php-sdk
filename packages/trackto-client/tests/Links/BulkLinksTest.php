<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\BulkResultData;
use ExpertSystems\TrackTo\Enums\BulkAction;
use ExpertSystems\TrackTo\Exceptions\ForbiddenException;
use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;
use ExpertSystems\TrackTo\Exceptions\NotFoundException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Exceptions\ValidationException;
use ExpertSystems\TrackTo\Requests\BulkLinksRequest;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * Sends one bulk call against a 200 mock and returns the mock, so the caller can read the request.
 *
 * @param  callable(LinksResource): BulkResultData  $call
 */
function bulkSend(callable $call, ?MockResponse $response = null): MockClient
{
    $mock = new MockClient([$response ?? MockResponse::make(Fixtures::bulk(), 200)]);

    $call(TestClient::make($mock)->links());

    return $mock;
}

/**
 * @param  callable(LinksResource): BulkResultData  $call
 * @param  array<string, mixed>  $body
 */
function bulkResult(callable $call, array $body): BulkResultData
{
    return $call(TestClient::make(new MockClient([MockResponse::make($body, 200)]))->links());
}

/**
 * @return array<string, mixed>
 */
function bulkBody(MockClient $mock): array
{
    return $mock->getLastPendingRequest()?->body()?->all() ?? [];
}

/**
 * @return list<string>
 */
function bulkSlugs(int $count): array
{
    // range(1, 0) counts down, so build the list with a loop bound instead.
    return array_map(static fn (int $i): string => 's'.$i, $count === 0 ? [] : range(1, $count));
}

it('sends one POST with the move body', function (): void {
    $mock = bulkSend(fn ($links) => $links->bulkMove(['a', 'b'], 7));
    $pending = $mock->getLastPendingRequest();

    expect($pending?->getMethod()->value)->toBe('POST')
        ->and($pending?->getUri()->getPath())->toBe('/links/bulk')
        ->and(bulkBody($mock))->toBe(['action' => 'move', 'slugs' => ['a', 'b'], 'folder_id' => 7]);
    $mock->assertSentCount(1);
});

it('sends folder_id null to unfile', function (): void {
    expect(bulkBody(bulkSend(fn ($links) => $links->bulkMove(['a'], null))))
        ->toBe(['action' => 'move', 'slugs' => ['a'], 'folder_id' => null]);
});

it('sends the tag body, keeping an empty tag list', function (array $tags): void {
    expect(bulkBody(bulkSend(fn ($links) => $links->bulkTag(['a'], $tags))))
        ->toBe(['action' => 'tag', 'slugs' => ['a'], 'tags' => $tags]);
})->with(['two tags' => [['x', 'y']], 'no tags' => [[]]]);

it('sends only action and slugs for a delete', function (): void {
    expect(bulkBody(bulkSend(fn ($links) => $links->bulkDelete(['a']))))
        ->toBe(['action' => 'delete', 'slugs' => ['a']]);
});

it('checks the count before any request, for 0, 1, 100 and 101 slugs', function (string $method, Closure $call): void {
    foreach ([0, 101] as $count) {
        $mock = new MockClient([MockResponse::make(Fixtures::bulk(), 200)]);

        expect(fn () => $call(TestClient::make($mock)->links(), bulkSlugs($count)))->toThrow(InvalidArgumentException::class);
        $mock->assertNothingSent();
    }

    foreach ([1, 100] as $count) {
        $mock = bulkSend(fn ($links) => $call($links, bulkSlugs($count)));

        expect(bulkBody($mock)['slugs'])->toHaveCount($count);
        $mock->assertSentCount(1);
    }
})->with([
    'move' => ['move', fn ($links, array $slugs): BulkResultData => $links->bulkMove($slugs, 1)],
    'tag' => ['tag', fn ($links, array $slugs): BulkResultData => $links->bulkTag($slugs, ['x'])],
    'delete' => ['delete', fn ($links, array $slugs): BulkResultData => $links->bulkDelete($slugs)],
]);

it('throws an exception that is also a built-in and a TrackToException', function (): void {
    try {
        TestClient::make(new MockClient([]))->links()->bulkDelete([]);
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(InvalidArgumentException::class)
            ->and($e)->toBeInstanceOf(TrackToException::class)
            ->and($e)->toBeInstanceOf(\InvalidArgumentException::class)
            ->and($e->getMessage())->toBe('Bulk actions take between 1 and 100 slugs.');

        return;
    }

    throw new RuntimeException('Expected an exception.');
});

it('counts duplicates toward the limit and sends them as given', function (): void {
    $mock = bulkSend(fn ($links) => $links->bulkDelete(['a', 'a']), MockResponse::make(Fixtures::bulk(['action' => 'delete', 'slugs' => ['a']]), 200));

    expect(bulkBody($mock)['slugs'])->toBe(['a', 'a']);

    $result = bulkResult(fn ($links) => $links->bulkDelete(['a', 'a']), Fixtures::bulk(['action' => 'delete', 'slugs' => ['a']]));
    expect($result->slugs)->toBe(['a'])->and($result->action)->toBe(BulkAction::Delete);

    expect(fn () => TestClient::make(new MockClient([]))->links()->bulkDelete(array_fill(0, 101, 'a')))->toThrow(InvalidArgumentException::class);
});

it('sends an associative slug array as a JSON list', function (): void {
    $mock = bulkSend(fn ($links) => $links->bulkDelete(['x' => 'a', 'y' => 'b']));

    expect(bulkBody($mock)['slugs'])->toBe(['a', 'b'])
        ->and((string) $mock->getLastPendingRequest()?->body())->toContain('"slugs":["a","b"]');
});

it('sends numeric-looking slugs as strings, in caller order', function (): void {
    $mock = bulkSend(fn ($links) => $links->bulkDelete(['007']));
    expect((string) $mock->getLastPendingRequest()?->body())->toContain('"slugs":["007"]');

    expect(bulkBody(bulkSend(fn ($links) => $links->bulkDelete(['c', 'a', 'b'])))['slugs'])->toBe(['c', 'a', 'b']);
});

it('hydrates an unknown action as Unknown', function (): void {
    $result = bulkResult(fn ($links) => $links->bulkDelete(['a']), Fixtures::bulk(['action' => 'archive']));

    expect($result->action)->toBe(BulkAction::Unknown);
});

it('raises UnexpectedResponseException for a malformed 2xx body', function (array $body): void {
    expect(fn () => TestClient::make(new MockClient([MockResponse::make($body, 200)]))->links()->bulkDelete(['a']))
        ->toThrow(UnexpectedResponseException::class);
})->with([
    'int slug' => [['data' => ['action' => 'delete', 'slugs' => [1]]]],
    'object slugs' => [['data' => ['action' => 'delete', 'slugs' => ['a' => 'b']]]],
    'no data' => [['action' => 'delete', 'slugs' => ['a']]],
]);

it('lists the unknown slugs on a 404', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('not_found', ['slugs' => ['missing-1', 'missing-2']])]);

    try {
        TestClient::make($mock)->links()->bulkDelete(['missing-1', 'missing-2']);
    } catch (NotFoundException $e) {
        expect($e->errorsFor('slugs'))->toBe(['missing-1', 'missing-2']);
        $mock->assertSentCount(1);

        return;
    }

    throw new RuntimeException('Expected NotFoundException.');
});

it('maps 403 and 422 to typed exceptions', function (string $name, string $class): void {
    $mock = new MockClient([Fixtures::errorResponse($name)]);

    expect(fn () => TestClient::make($mock)->links()->bulkDelete(['a']))->toThrow($class);
})->with([['forbidden', ForbiddenException::class], ['validation_error', ValidationException::class]]);

it('refuses to build a request for the Unknown action', function (): void {
    expect(fn () => new BulkLinksRequest(BulkAction::Unknown, ['a']))->toThrow(InvalidArgumentException::class);
});

it('declares a single attempt on the request', function (): void {
    expect((new BulkLinksRequest(BulkAction::Delete, ['a']))->tries)->toBe(1);
});
