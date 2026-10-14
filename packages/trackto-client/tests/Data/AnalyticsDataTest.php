<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\AnalyticsCountData;
use ExpertSystems\TrackTo\Data\AnalyticsDayData;
use ExpertSystems\TrackTo\Data\LinkAnalyticsData;
use ExpertSystems\TrackTo\Testing\Fixtures;

it('hydrates every analytics field', function (): void {
    $analytics = LinkAnalyticsData::fromArray(Fixtures::analytics());

    expect($analytics->link->slug)->toBe('abc123')
        ->and($analytics->total)->toBe(5)
        ->and($analytics->overTime[1])->toEqual(new AnalyticsDayData('2026-07-06', 3))
        ->and($analytics->topReferrers[0])->toEqual(new AnalyticsCountData('example.com', 3))
        ->and($analytics->topCountries[1]->label)->toBe('Unknown');
});

it('hydrates an all-empty body as zero and three empty lists', function (): void {
    $analytics = LinkAnalyticsData::fromArray(Fixtures::analytics(['total' => 0, 'overTime' => [], 'topReferrers' => [], 'topCountries' => []]));

    expect($analytics->total)->toBe(0)
        ->and($analytics->overTime)->toBe([])
        ->and($analytics->topReferrers)->toBe([])
        ->and($analytics->topCountries)->toBe([]);
});

it('rejects a body missing a required key', function (string $key): void {
    $body = Fixtures::analytics();
    unset($body[$key]);

    expect(fn () => LinkAnalyticsData::fromArray($body))->toThrow(UnexpectedValueException::class);
})->with(['link', 'total', 'overTime', 'topReferrers', 'topCountries']);

it('rejects a date that is not a real Y-m-d day', function (string $date): void {
    $body = Fixtures::analytics(['overTime' => [['date' => $date, 'count' => 1]]]);

    expect(fn () => LinkAnalyticsData::fromArray($body))->toThrow(UnexpectedValueException::class);
})->with(['rolled-over day' => '2026-02-31', 'no zero padding' => '2026-7-6', 'time part' => '2026-07-06T00:00:00Z', 'no separators' => '20260706']);

it('rejects wrong types without echoing the value', function (array $overrides): void {
    try {
        LinkAnalyticsData::fromArray(Fixtures::analytics($overrides));
    } catch (UnexpectedValueException $e) {
        expect($e->getMessage())->not->toContain('secret-value');

        return;
    }

    $this->fail('No exception thrown.');
})->with([
    'string count' => [['overTime' => [['date' => '2026-07-06', 'count' => '3']]]],
    'string total' => [['total' => 'secret-value']],
    'item is a string' => [['topReferrers' => ['secret-value']]],
    'object instead of list' => [['overTime' => ['a' => ['date' => '2026-07-06', 'count' => 1]]]],
    'link is a string' => [['link' => 'secret-value']],
    'label is an int' => [['topCountries' => [['label' => 1, 'count' => 1]]]],
]);
