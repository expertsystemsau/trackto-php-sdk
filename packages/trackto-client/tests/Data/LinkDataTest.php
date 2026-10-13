<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\FolderData;
use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\TagData;
use ExpertSystems\TrackTo\Enums\LinkStatus;
use ExpertSystems\TrackTo\Testing\Fixtures;

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
