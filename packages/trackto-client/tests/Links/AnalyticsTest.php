<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\AnalyticsDayData;
use ExpertSystems\TrackTo\Data\LinkAnalyticsData;
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
