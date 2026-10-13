<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Tests\Support;

use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Config;
use Saloon\Http\Faking\MockClient;

final class TestClient
{
    public const string API_KEY = 'tk_test_sentinel_key';

    private static bool $strayArmed = false;

    public static function make(MockClient $mockClient, string $apiKey = self::API_KEY): TrackToClient
    {
        // Each call appends a global middleware, so arm it once per process.
        if (! self::$strayArmed) {
            Config::preventStrayRequests();
            self::$strayArmed = true;
        }

        return (new TrackToClient(apiKey: $apiKey))->withMockClient($mockClient);
    }
}
