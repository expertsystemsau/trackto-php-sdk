<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Facades;

use ExpertSystems\TrackTo\Resources\FoldersResource;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Resources\TagsResource;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Support\Facades\Facade;
use Saloon\Http\Faking\MockClient;

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
    protected static function getFacadeAccessor(): string
    {
        return TrackToClient::class;
    }
}
