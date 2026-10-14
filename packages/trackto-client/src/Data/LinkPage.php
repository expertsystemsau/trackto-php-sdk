<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use UnexpectedValueException;

final readonly class LinkPage
{
    /**
     * @param  list<LinkData>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * Takes the whole response body: pagination meta sits beside `data`. The `links.*` URLs are never read.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body): self
    {
        $data = $body['data'] ?? null;
        $meta = $body['meta'] ?? null;

        if (! is_array($data) || ! is_array($meta)) {
            throw new UnexpectedValueException('The links page has no data list or meta object.');
        }

        $items = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException('A links page item is not an object.');
            }

            /** @var array<string, mixed> $item */
            $items[] = LinkData::fromArray($item);
        }

        return new self(
            $items,
            self::int($meta, 'current_page'),
            self::int($meta, 'last_page'),
            self::int($meta, 'per_page'),
            self::int($meta, 'total'),
        );
    }

    /**
     * @param  array<mixed>  $meta
     */
    private static function int(array $meta, string $key): int
    {
        $value = $meta[$key] ?? null;

        return is_int($value) ? $value : throw new UnexpectedValueException("Page meta [{$key}] is missing or has the wrong type.");
    }
}
