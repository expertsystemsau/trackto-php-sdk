<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Tests\Support;

use ExpertSystems\TrackTo\Support\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<float> */
    public array $waits = [];

    public function sleep(float $seconds): void
    {
        $this->waits[] = $seconds;
    }
}
