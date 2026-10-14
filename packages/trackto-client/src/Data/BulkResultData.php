<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use ExpertSystems\TrackTo\Enums\BulkAction;
use UnexpectedValueException;

final readonly class BulkResultData
{
    /**
     * @param  list<string>  $slugs  The server's deduplicated slugs
     */
    public function __construct(
        public BulkAction $action,
        public array $slugs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $slugs = $data['slugs'] ?? null;

        if (! is_array($slugs) || ! array_is_list($slugs) || array_filter($slugs, is_string(...)) !== $slugs) {
            throw new UnexpectedValueException('Bulk result slugs must be a list of strings.');
        }

        return new self(BulkAction::fromApi($data['action'] ?? null), $slugs);
    }
}
