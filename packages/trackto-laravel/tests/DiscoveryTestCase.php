<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests;

use Illuminate\Foundation\PackageManifest;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class DiscoveryTestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function resolveApplicationResolvingCallback($app): void
    {
        parent::resolveApplicationResolvingCallback($app);

        // Under `pest --parallel` Testbench uses a per-worker manifest that never contains the root
        // extra.laravel, so feed it the package's own discovery metadata, which is what a consuming app reads.
        $composer = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $app->make(PackageManifest::class)->manifest = ['expertsystemsau/trackto-laravel-client' => $composer['extra']['laravel']];
    }
}
