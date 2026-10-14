<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use UnexpectedValueException;

final readonly class TagData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $color,
        public ?int $linksCount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $color = $data['color'] ?? null;
        $linksCount = $data['links_count'] ?? null;

        if (! is_int($id) || ! is_string($name) || ! is_string($color)) {
            throw new UnexpectedValueException('Tag is missing a valid id, name or color.');
        }

        return new self($id, $name, $color, is_int($linksCount) ? $linksCount : null);
    }
}
