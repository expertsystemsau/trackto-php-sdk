<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Data;

use DateTimeImmutable;
use Exception;
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
            self::folder($folder),
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

    /**
     * Only an RFC 3339 timestamp parses; anything else is null, never a relative or "now" date.
     */
    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/i', $value) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * An optional folder that is malformed is dropped rather than failing the whole page.
     */
    private static function folder(mixed $folder): ?FolderData
    {
        try {
            return is_array($folder) ? FolderData::fromArray($folder) : null;
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    /**
     * @return list<TagData>
     */
    private static function tags(mixed $tags): array
    {
        $result = [];

        foreach (is_array($tags) ? $tags : [] as $tag) {
            try {
                if (is_array($tag)) {
                    $result[] = TagData::fromArray($tag);
                }
            } catch (UnexpectedValueException) {
                // A malformed tag is skipped, like a tag that is not an object.
            }
        }

        return $result;
    }
}
