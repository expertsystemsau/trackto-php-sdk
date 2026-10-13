<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Tests\Support;

use ExpertSystems\TrackTo\TrackToClient;
use RuntimeException;
use Saloon\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Throwable;

final class TestClient
{
    public const string API_KEY = 'tk_test_sentinel_key';

    private static bool $strayArmed = false;

    public static function make(MockClient $mockClient, ?RecordingSleeper $sleeper = null, string $apiKey = self::API_KEY): TrackToClient
    {
        // Each call appends a global middleware, so arm it once per process.
        if (! self::$strayArmed) {
            Config::preventStrayRequests();
            self::$strayArmed = true;
        }

        return (new TrackToClient(apiKey: $apiKey, sleeper: $sleeper ?? new RecordingSleeper))->withMockClient($mockClient);
    }

    /**
     * The exception a single links()->get() raises against the given mock response. Retry is off:
     * the error tests prove mapping, and a GET 5xx would otherwise consume three mocked responses.
     */
    public static function failure(MockResponse $response): Throwable
    {
        try {
            self::make(new MockClient([$response]))->withoutRetry()->links()->get('x');
        } catch (Throwable $e) {
            return $e;
        }

        throw new RuntimeException('Expected an exception.');
    }
}
