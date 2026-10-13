<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Facades;

use ExpertSystems\TrackTo\Laravel\ClientFactory;
use ExpertSystems\TrackTo\Resources\FoldersResource;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Resources\TagsResource;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Support\Facades\Facade;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * @method static LinksResource links()
 * @method static FoldersResource folders()
 * @method static TagsResource tags()
 * @method static TrackToClient withRetry(int $tries, float $maxWait = 3.0)
 * @method static TrackToClient withoutRetry()
 * @method static TrackToClient withMockClient(MockClient $mockClient)
 * @method static TrackToClient withApiKey(string $apiKey)
 * @method static TrackToClient withBaseUrl(string $baseUrl)
 * @method static TrackToClient withVersion(?string $version)
 * @method static TrackToClient withConnectTimeout(float $seconds)
 * @method static TrackToClient withTimeout(float $seconds)
 * @method static TrackToClient withUserAgentSuffix(?string $suffix)
 *
 * @see TrackToClient
 */
final class TrackTo extends Facade
{
    /**
     * Swaps a client carrying a fresh MockClient into the facade and the container, and returns the mock for
     * assertSent*(). Call it before the code under test resolves the client: an object that already stored
     * the real client keeps it.
     *
     * @param  array<array-key, MockResponse|Fixture|callable>  $responses  Saloon's mock data: a sequence, or a map keyed by request class or URL
     */
    public static function fake(array $responses = []): MockClient
    {
        $mock = new MockClient($responses);

        /** @var array<string, mixed> $config */
        $config = (array) self::getFacadeApplication()?->make('config')->get('trackto', []);

        self::swap(ClientFactory::make($config, fallbackKey: 'fake-api-key')->withMockClient($mock));

        return $mock;
    }

    protected static function getFacadeAccessor(): string
    {
        return TrackToClient::class;
    }
}
