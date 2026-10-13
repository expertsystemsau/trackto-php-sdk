<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Enums;

enum BulkAction: string
{
    case Move = 'move';
    case Tag = 'tag';
    case Delete = 'delete';
    case Unknown = 'unknown';

    /** Tolerant reader: a value this SDK does not know yet is Unknown, never an error. */
    public static function fromApi(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Unknown) : self::Unknown;
    }
}
