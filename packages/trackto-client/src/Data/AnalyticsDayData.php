<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use DateTimeImmutable;
use UnexpectedValueException;

final readonly class AnalyticsDayData
{
    /**
     * @param  string  $date  A `Y-m-d` calendar day: a day bucket has no time or zone.
     */
    public function __construct(
        public string $date,
        public int $count,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $date = $data['date'] ?? null;
        $count = $data['count'] ?? null;

        $day = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;

        // The round trip rejects 2026-02-31, which createFromFormat rolls over, and any time part.
        if ($day === false || $day->format('Y-m-d') !== $date) {
            throw new UnexpectedValueException('Analytics day field [date] is missing or is not a Y-m-d day.');
        }

        if (! is_int($count)) {
            throw new UnexpectedValueException('Analytics day field [count] is missing or has the wrong type.');
        }

        return new self($date, $count);
    }
}
