<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;
use ExpertSystems\TrackTo\TrackToClient;

it('pins the default Track-Version to the spec version', function (): void {
    expect(Checks::versionPin(Checks::spec(), TrackToClient::DEFAULT_VERSION))->toBe([]);
});

it('reports a drifted spec version', function (): void {
    $spec = Checks::spec();
    $spec['info']['version'] = '2027-01-01';

    $problems = Checks::versionPin($spec, TrackToClient::DEFAULT_VERSION);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('2027-01-01', TrackToClient::DEFAULT_VERSION);
});
