<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Support;

/**
 * @internal
 */
final class UsleepSleeper implements Sleeper
{
    public function sleep(float $seconds): void
    {
        usleep((int) round(max(0.0, $seconds) * 1_000_000));
    }
}
