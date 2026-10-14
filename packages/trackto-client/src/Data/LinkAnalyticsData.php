<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use UnexpectedValueException;

final readonly class LinkAnalyticsData
{
    /**
     * @param  list<AnalyticsDayData>  $overTime
     * @param  list<AnalyticsCountData>  $topReferrers
     * @param  list<AnalyticsCountData>  $topCountries
     */
    public function __construct(
        public LinkData $link,
        public int $total,
        public array $overTime,
        public array $topReferrers,
        public array $topCountries,
    ) {}

    /**
     * Takes the whole response body: the analytics endpoint has no `data` envelope. Every field is required.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $link = $data['link'] ?? null;
        $total = $data['total'] ?? null;

        if (! is_array($link)) {
            throw new UnexpectedValueException('Analytics field [link] is missing or has the wrong type.');
        }

        if (! is_int($total)) {
            throw new UnexpectedValueException('Analytics field [total] is missing or has the wrong type.');
        }

        /** @var array<string, mixed> $link */
        return new self(
            LinkData::fromArray($link),
            $total,
            self::items($data, 'overTime', AnalyticsDayData::fromArray(...)),
            self::items($data, 'topReferrers', AnalyticsCountData::fromArray(...)),
            self::items($data, 'topCountries', AnalyticsCountData::fromArray(...)),
        );
    }

    /**
     * @template T of object
     *
     * @param  array<string, mixed>  $data
     * @param  callable(array<string, mixed>): T  $build
     * @return list<T>
     */
    private static function items(array $data, string $key, callable $build): array
    {
        $list = $data[$key] ?? null;

        if (! is_array($list) || ! array_is_list($list)) {
            throw new UnexpectedValueException("Analytics field [{$key}] is missing or is not a list.");
        }

        $items = [];

        foreach ($list as $item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException("Analytics field [{$key}] holds an item that is not an object.");
            }

            /** @var array<string, mixed> $item */
            $items[] = $build($item);
        }

        return $items;
    }
}
