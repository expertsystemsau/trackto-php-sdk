<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Enums;

/**
 * Input only: the server falls back to `recent` on unknown values, so a new sort key needs an SDK release.
 */
enum LinkSort: string
{
    case Recent = 'recent';
    case Clicks = 'clicks';
    case Name = 'name';
}
