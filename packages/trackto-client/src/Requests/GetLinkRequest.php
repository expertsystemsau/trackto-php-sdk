<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Support\SlugPath;
use Saloon\Http\Response;

final class GetLinkRequest extends TrackToRequest
{
    private readonly string $segment;

    public function __construct(string $slug)
    {
        $this->segment = SlugPath::encode($slug);
    }

    public function resolveEndpoint(): string
    {
        return '/links/'.$this->segment;
    }

    public function createDtoFromResponse(Response $response): LinkData
    {
        return $this->hydrate($response, fn (): LinkData => LinkData::fromArray($this->payload($response)));
    }
}
