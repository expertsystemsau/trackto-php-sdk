<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\TrackToClient;

it('instantiates the client', function (): void {
    expect(new TrackToClient)->toBeInstanceOf(TrackToClient::class);
});
