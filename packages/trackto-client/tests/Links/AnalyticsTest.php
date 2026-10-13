<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\AnalyticsDayData;
use ExpertSystems\TrackTo\Data\LinkAnalyticsData;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('returns typed analytics read from the unwrapped body, in API order', function (): void {
    $mock = new MockClient([MockResponse::make(Fixtures::analytics(['link' => Fixtures::link(['slug' => '007'])]), 200)]);

    $analytics = TestClient::make($mock)->links()->analytics('007');
    $pending = $mock->getLastPendingRequest();

    expect($analytics)->toBeInstanceOf(LinkAnalyticsData::class)
        ->and($analytics->link->slug)->toBeString()->toBe('007')
        ->and($analytics->total)->toBe(5)
        ->and($analytics->overTime)->toHaveCount(2)->each->toBeInstanceOf(AnalyticsDayData::class)
        ->and(array_map(static fn (AnalyticsDayData $day): string => $day->date, $analytics->overTime))->toBe(['2026-07-05', '2026-07-06'])
        ->and(array_column($analytics->topReferrers, 'label'))->toBe(['example.com', 'Direct'])
        ->and(array_column($analytics->topCountries, 'label'))->toBe(['AU', 'Unknown'])
        ->and($pending?->getMethod()->value)->toBe('GET')
        ->and($pending?->getUri()->getPath())->toBe('/links/007/analytics');
    $mock->assertSentCount(1);
});

it('raises UnexpectedResponseException for a malformed analytics body after one request', function (array $body): void {
    $mock = new MockClient([MockResponse::make($body, 200)]);

    expect(fn () => TestClient::make($mock)->links()->analytics('x'))->toThrow(UnexpectedResponseException::class);
    $mock->assertSentCount(1);
})->with([
    'wrapped in data' => fn (): array => ['data' => Fixtures::analytics()],
    'missing total' => function (): array {
        $body = Fixtures::analytics();
        unset($body['total']);

        return $body;
    },
]);

it('percent-encodes the slug into a single analytics path segment', function (string $slug, string $path): void {
    $mock = new MockClient([MockResponse::make(Fixtures::analytics(), 200)]);

    TestClient::make($mock)->links()->analytics($slug);

    expect($mock->getLastPendingRequest()?->getUri()->getPath())->toBe($path);
})->with([
    'inner dot' => ['v1.2', '/links/v1%2E2/analytics'],
    'space and slash' => ['a b/c', '/links/a%20b%2Fc/analytics'],
]);
