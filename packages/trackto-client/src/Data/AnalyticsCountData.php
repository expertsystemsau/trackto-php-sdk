<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use UnexpectedValueException;

final readonly class AnalyticsCountData
{
    public function __construct(
        public string $label,
        public int $count,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $label = $data['label'] ?? null;
        $count = $data['count'] ?? null;

        if (! is_string($label)) {
            throw new UnexpectedValueException('Analytics count field [label] is missing or has the wrong type.');
        }

        if (! is_int($count)) {
            throw new UnexpectedValueException('Analytics count field [count] is missing or has the wrong type.');
        }

        return new self($label, $count);
    }
}
