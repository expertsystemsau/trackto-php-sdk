<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Exceptions\ValidationException;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Support\Omitted;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

const UPDATE_URL = 'https://example.com/new';

/**
 * Sends one update and returns the decoded PATCH body.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function updateBody(array $arguments = [], ?MockClient &$mock = null): array
{
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    TestClient::make($mock)->links()->update(...['slug' => 'abc123', 'destinationUrl' => UPDATE_URL, ...$arguments]);

    return $mock->getLastPendingRequest()?->body()?->all() ?? [];
}

it('sends one PATCH with only the destination url when nothing else is given', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    $link = TestClient::make($mock)->links()->update(slug: 'abc123', destinationUrl: UPDATE_URL);
    $pending = $mock->getLastPendingRequest();

    expect($link)->toBeInstanceOf(LinkData::class)
        ->and($pending?->getMethod()->value)->toBe('PATCH')
        ->and($pending?->getUri()->getPath())->toBe('/links/abc123')
        ->and($pending?->body()?->all())->toBe(['destination_url' => UPDATE_URL]);
    $mock->assertSentCount(1);
});

it('builds the exact body for every state of expiry, folder and tags', function (array $arguments, array $expected): void {
    expect(updateBody($arguments))->toBe(['destination_url' => UPDATE_URL, ...$expected]);
})->with([
    'expiry omitted' => [['expiresAt' => Omitted::Value], []],
    'expiry cleared' => [['expiresAt' => null], ['expires_at' => null]],
    'expiry set' => [['expiresAt' => new DateTimeImmutable('2026-12-01T09:30:00+10:00')], ['expires_at' => '2026-12-01T09:30:00+10:00']],
    'folder omitted' => [['folderId' => Omitted::Value], []],
    'folder cleared' => [['folderId' => null], ['folder_id' => null]],
    'folder set' => [['folderId' => 7], ['folder_id' => 7]],
    'tags omitted' => [['tags' => Omitted::Value], []],
    'tags cleared' => [['tags' => []], ['tags' => []]],
    'tags set' => [['tags' => ['a', 'b']], ['tags' => ['a', 'b']]],
    'rename omitted' => [['newSlug' => Omitted::Value], []],
    'rename set' => [['newSlug' => 'renamed'], ['slug' => 'renamed']],
    'password omitted' => [['password' => null], []],
    'password set' => [['password' => 'secret-pass-1'], ['password' => 'secret-pass-1']],
    'password removed' => [['removePassword' => true], ['remove_password' => true]],
]);

it('never lets an omitted field and its cleared form produce the same body', function (string $field, mixed $cleared): void {
    expect(updateBody())->not->toBe(updateBody([$field => $cleared]));
})->with([
    'expiry' => ['expiresAt', null],
    'folder' => ['folderId', null],
    'tags' => ['tags', []],
    'password' => ['removePassword', true],
]);

it('returns the link carrying the new slug after a rename', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link(['slug' => 'renamed'])], 200)]);

    $link = TestClient::make($mock)->links()->update(slug: 'abc123', destinationUrl: UPDATE_URL, newSlug: 'renamed');

    expect($link->slug)->toBe('renamed')
        ->and($mock->getLastPendingRequest()?->body()?->all())->toBe(['destination_url' => UPDATE_URL, 'slug' => 'renamed']);
});

it('treats tags: null as a TypeError and sends nothing', function (): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    expect(fn () => TestClient::make($mock)->links()->update(slug: 'abc123', destinationUrl: UPDATE_URL, tags: null))
        ->toThrow(TypeError::class);
    $mock->assertNothingSent();
});

it('sends an update that gets a 500 exactly once, with the default retry on', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('server_error'), MockResponse::make(['data' => Fixtures::link()], 200)]);

    expect(fn () => TestClient::make($mock)->links()->update(slug: 'abc123', destinationUrl: UPDATE_URL))
        ->toThrow(ServerException::class);
    $mock->assertSentCount(1);
});

it('raises ValidationException with field errors on a 422', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('validation_error', ['destination_url' => ['The destination url field is required.']])]);

    try {
        TestClient::make($mock)->links()->update(slug: 'abc123', destinationUrl: '');
    } catch (ValidationException $e) {
        expect($e->errorsFor('destination_url'))->toBe(['The destination url field is required.']);

        return;
    }

    throw new RuntimeException('Expected ValidationException.');
});

it('never reads before it writes, so there is no read-modify-write race', function (): void {
    updateBody(['expiresAt' => null, 'tags' => ['x']], $mock);

    $mock->assertSentCount(1);
    expect($mock->getLastPendingRequest()?->getMethod()->value)->toBe('PATCH');
});

it('pins the public parameter names and order of update()', function (): void {
    $names = array_map(
        static fn (ReflectionParameter $p): string => $p->getName(),
        (new ReflectionMethod(LinksResource::class, 'update'))->getParameters(),
    );

    expect($names)->toBe(['slug', 'destinationUrl', 'newSlug', 'expiresAt', 'folderId', 'tags', 'password', 'removePassword']);
});

it('refuses a password together with removePassword before sending anything', function (?string $password): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    try {
        TestClient::make($mock)->links()->update(slug: 'abc123', destinationUrl: UPDATE_URL, password: $password, removePassword: true);
    } catch (InvalidArgumentException $e) {
        expect($e)->toBeInstanceOf(\InvalidArgumentException::class)
            ->and($e)->toBeInstanceOf(TrackToException::class)
            ->and($e->getMessage())->toBe('Pass either a password or removePassword: true, not both.');
        $mock->assertNothingSent();

        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException.');
})->with(['password' => ['secret-pass-1'], 'empty password' => ['']]);

it('keeps the password out of the conflict message', function (): void {
    try {
        updateBody(['password' => 'secret-pass-1', 'removePassword' => true]);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain('secret-pass-1');

        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException.');
});

it('sends a numeric-looking slug as a path segment and as a JSON string', function (): void {
    $body = updateBody(['slug' => '007', 'newSlug' => '007'], $mock);

    expect($mock->getLastPendingRequest()?->getUri()->getPath())->toBe('/links/007')
        ->and((string) $mock->getLastPendingRequest()?->body())->toContain('"slug":"007"')
        ->and($body['slug'])->toBe('007');
});

it('sends tag names in caller order and an associative array as a list', function (): void {
    expect(updateBody(['tags' => ['zeta', 'alpha']])['tags'])->toBe(['zeta', 'alpha'])
        ->and(updateBody(['tags' => ['x' => 'zeta', 'y' => 'alpha']], $mock)['tags'])->toBe(['zeta', 'alpha'])
        ->and((string) $mock->getLastPendingRequest()?->body())->toContain('"tags":["zeta","alpha"]');
});

it('formats expiry as DATE_ATOM with its own offset and drops microseconds', function (DateTimeInterface $expiresAt, string $expected): void {
    expect(updateBody(['expiresAt' => $expiresAt])['expires_at'])->toBe($expected);
})->with([
    'sydney with microseconds' => [new DateTimeImmutable('2026-12-01 09:30:00.123456', new DateTimeZone('Australia/Sydney')), '2026-12-01T09:30:00+11:00'],
    'mutable utc' => [new DateTime('2026-12-01 09:30:00', new DateTimeZone('UTC')), '2026-12-01T09:30:00+00:00'],
]);
