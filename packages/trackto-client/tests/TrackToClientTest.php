<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;

it('constructs from just an API key', function (): void {
    $client = new TrackToClient(apiKey: TestClient::API_KEY);

    expect($client)->toBeInstanceOf(TrackToClient::class)
        ->and($client->resolveBaseUrl())->toBe(TrackToClient::DEFAULT_BASE_URL);
});
