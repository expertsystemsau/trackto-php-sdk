<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests;

use ExpertSystems\TrackTo\Laravel\TrackToServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TrackToServiceProvider::class,
        ];
    }
}
