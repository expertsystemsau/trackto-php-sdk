<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\TrackToServiceProvider;

it('loads the service provider', function (): void {
    expect(app()->providerIsLoaded(TrackToServiceProvider::class))->toBeTrue();
});
