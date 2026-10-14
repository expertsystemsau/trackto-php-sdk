<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\LinkAnalyticsData;
use ExpertSystems\TrackTo\Support\SlugPath;
use Saloon\Http\Response;

final class GetLinkAnalyticsRequest extends TrackToRequest
{
    private readonly string $segment;

    public function __construct(string $slug)
    {
        $this->segment = SlugPath::encode($slug);
    }

    public function resolveEndpoint(): string
    {
        return '/links/'.$this->segment.'/analytics';
    }

    /**
     * The analytics body has no `data` envelope, so it is read whole.
     */
    public function createDtoFromResponse(Response $response): LinkAnalyticsData
    {
        return $this->hydrate($response, fn (): LinkAnalyticsData => LinkAnalyticsData::fromArray($this->decode($response)));
    }
}
