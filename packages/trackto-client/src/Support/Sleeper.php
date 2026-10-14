<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Support;

/**
 * The wait seam for retries, so tests never really sleep.
 *
 * @internal
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
