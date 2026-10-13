<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Support;

use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;

/**
 * @internal
 */
final class SlugPath
{
    /**
     * Encodes a link slug into one URL path segment.
     *
     * @throws InvalidArgumentException
     */
    public static function encode(string $slug): string
    {
        // A blank slug would turn /links/{slug} into /links/, the list endpoint.
        if (trim($slug) === '') {
            throw new InvalidArgumentException('The link slug must not be blank.');
        }

        // rawurlencode leaves dots alone; encode them so `.` and `..` never become dot segments.
        return str_replace('.', '%2E', rawurlencode($slug));
    }
}
