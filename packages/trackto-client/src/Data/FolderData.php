<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use UnexpectedValueException;

final readonly class FolderData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $linksCount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $linksCount = $data['links_count'] ?? null;

        if (! is_int($id) || ! is_string($name)) {
            throw new UnexpectedValueException('Folder is missing a valid id or name.');
        }

        return new self($id, $name, is_int($linksCount) ? $linksCount : null);
    }
}
