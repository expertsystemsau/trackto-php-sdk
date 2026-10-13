<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\FolderData;
use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\TagData;
use ExpertSystems\TrackTo\Enums\LinkStatus;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockResponse;

it('hydrates an unrecognised status as Unknown', function (mixed $status): void {
    expect(LinkData::fromArray(Fixtures::link(['status' => $status]))->status)->toBe(LinkStatus::Unknown);
})->with(['new value' => ['archived'], 'null' => [null]]);

it('ignores extra top-level and nested fields', function (): void {
    $link = LinkData::fromArray(Fixtures::link([
        'utm_source' => 'x',
        'folder' => ['id' => 2, 'name' => 'B', 'colour' => 'red'],
    ]));

    expect($link->folder?->name)->toBe('B');
});

it('is readonly', function (): void {
    $link = LinkData::fromArray(Fixtures::link());

    expect(fn () => $link->slug = 'x')->toThrow(Error::class);
});

it('hydrates dates, folder and tags, tolerating nulls', function (): void {
    $link = LinkData::fromArray(Fixtures::link(['expires_at' => '2026-12-01T09:30:00+10:00']));

    expect($link->expiresAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($link->expiresAt?->getTimestamp())->toBe((new DateTimeImmutable('2026-12-01T09:30:00+10:00'))->getTimestamp());

    $bare = LinkData::fromArray(Fixtures::link(['folder' => null, 'tags' => [], 'created_at' => null]));

    expect($bare->folder)->toBeNull()
        ->and($bare->tags)->toBe([])
        ->and($bare->createdAt)->toBeNull()
        ->and($bare->expiresAt)->toBeNull();
});

it('throws on a missing or wrong-typed required field without echoing its value', function (): void {
    expect(fn () => LinkData::fromArray(Fixtures::link(['click_count' => 's3cret'])))
        ->toThrow(fn (UnexpectedValueException $e) => expect($e->getMessage())->toContain('click_count')->not->toContain('s3cret'));
});

it('carries a nullable linksCount on folders and tags', function (): void {
    expect(FolderData::fromArray(['id' => 1, 'name' => 'A', 'links_count' => 3])->linksCount)->toBe(3)
        ->and(FolderData::fromArray(['id' => 1, 'name' => 'A'])->linksCount)->toBeNull()
        ->and(TagData::fromArray(['id' => 1, 'name' => 'A', 'color' => '#fff', 'links_count' => 3])->linksCount)->toBe(3)
        ->and(TagData::fromArray(['id' => 1, 'name' => 'A', 'color' => '#fff'])->linksCount)->toBeNull();
});

it('raises UnexpectedResponseException for a 2xx body that cannot hydrate', function (string $body): void {
    $e = TestClient::failure(MockResponse::make($body, 200));

    expect($e)->toBeInstanceOf(UnexpectedResponseException::class)
        ->and($e->status())->toBe(200)
        ->and($e->getMessage())->not->toContain($body);
})->with([
    'empty object' => '{}',
    'data not an object' => '{"data": "x"}',
    'missing slug' => fn () => json_encode(['data' => array_diff_key(Fixtures::link(), ['slug' => 1])]),
    'string id' => fn () => json_encode(['data' => Fixtures::link(['id' => '1'])]),
    'html' => '<html><body>Hi</body></html>',
]);

it('reads an optional date it cannot parse as null, never as now or a relative date', function (mixed $value): void {
    $link = LinkData::fromArray(Fixtures::link(['expires_at' => $value, 'created_at' => $value]));

    expect($link->expiresAt)->toBeNull()->and($link->createdAt)->toBeNull();
})->with(['empty' => '', 'blank' => '  ', 'relative' => '+1 day', 'word' => 'tomorrow', 'garbage' => 'not a date', 'date only' => '2026-12-01', 'number' => 12345, 'impossible' => '2026-13-45T99:00:00Z']);

it('reads a calendar overflow or a trailing newline as null', function (string $value): void {
    $link = LinkData::fromArray(Fixtures::link(['expires_at' => $value, 'created_at' => $value]));

    expect($link->expiresAt)->toBeNull()->and($link->createdAt)->toBeNull();
})->with([
    'feb 31' => '2026-02-31T00:00:00Z',
    'apr 31' => '2026-04-31T10:00:00Z',
    'trailing newline' => "2026-12-01T09:30:00Z\n",
]);

it('still parses fractional, offset and lower-case z forms', function (string $value): void {
    expect(LinkData::fromArray(Fixtures::link(['expires_at' => $value]))->expiresAt)->toBeInstanceOf(DateTimeImmutable::class);
})->with([
    'fraction' => '2026-12-01T09:30:00.123456Z',
    'colon offset' => '2026-12-01T09:30:00+10:00',
    'compact offset' => '2026-12-01T09:30:00+1000',
    'lower z' => '2026-12-01T09:30:00z',
]);

it('accepts Z and fractional-second timestamps', function (): void {
    $link = LinkData::fromArray(Fixtures::link(['expires_at' => '2026-12-01T09:30:00.123456Z']));

    expect($link->expiresAt?->format('Y-m-d H:i'))->toBe('2026-12-01 09:30');
});

it('drops a malformed optional folder or tag instead of failing the link', function (): void {
    $link = LinkData::fromArray(Fixtures::link([
        'folder' => ['id' => 2],
        'tags' => [['id' => 1, 'name' => 'ok', 'color' => '#fff'], ['id' => 'x'], 'junk'],
    ]));

    expect($link->folder)->toBeNull()
        ->and($link->tags)->toHaveCount(1)
        ->and($link->tags[0]->name)->toBe('ok');
});
