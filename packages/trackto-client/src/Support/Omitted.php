<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Support;

/**
 * The default for an update argument the caller did not pass, meaning "leave unchanged".
 *
 * Import it only to forward "unchanged" explicitly, for example `expiresAt: $date ?? Omitted::Value`.
 */
enum Omitted
{
    case Value;
}
