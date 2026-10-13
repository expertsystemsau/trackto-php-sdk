<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use DateTimeImmutable;
use ExpertSystems\TrackTo\Enums\LinkStatus;
use UnexpectedValueException;

final readonly class LinkData
{
    /**
     * @param  list<TagData>  $tags
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $destinationUrl,
        public string $shortUrl,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $createdAt,
        public ?FolderData $folder,
        public array $tags,
        public bool $hasPassword,
        public LinkStatus $status,
        public int $clickCount,
    ) {}

    /**
     * Unknown keys are ignored; required scalars are type-checked without echoing their values.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;
        $destinationUrl = $data['destination_url'] ?? null;
        $shortUrl = $data['short_url'] ?? null;
        $hasPassword = $data['has_password'] ?? null;
        $clickCount = $data['click_count'] ?? null;
        $folder = $data['folder'] ?? null;
        $tags = $data['tags'] ?? [];

        if (! is_int($id)) {
            self::invalid('id');
        }

        if (! is_string($slug)) {
            self::invalid('slug');
        }

        if (! is_string($destinationUrl)) {
            self::invalid('destination_url');
        }

        if (! is_string($shortUrl)) {
            self::invalid('short_url');
        }

        if (! is_bool($hasPassword)) {
            self::invalid('has_password');
        }

        if (! is_int($clickCount)) {
            self::invalid('click_count');
        }

        return new self(
            $id,
            $slug,
            $destinationUrl,
            $shortUrl,
            self::date($data['expires_at'] ?? null),
            self::date($data['created_at'] ?? null),
            is_array($folder) ? FolderData::fromArray($folder) : null,
            self::tags($tags),
            $hasPassword,
            LinkStatus::fromApi($data['status'] ?? null),
            $clickCount,
        );
    }

    private static function invalid(string $field): never
    {
        throw new UnexpectedValueException("Link field [{$field}] is missing or has the wrong type.");
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) ? new DateTimeImmutable($value) : null;
    }

    /**
     * @return list<TagData>
     */
    private static function tags(mixed $tags): array
    {
        $result = [];

        foreach (is_array($tags) ? $tags : [] as $tag) {
            if (is_array($tag)) {
                $result[] = TagData::fromArray($tag);
            }
        }

        return $result;
    }
}
