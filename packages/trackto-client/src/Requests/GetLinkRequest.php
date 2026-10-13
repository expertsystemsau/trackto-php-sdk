<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\LinkData;
use Saloon\Http\Response;

final class GetLinkRequest extends TrackToRequest
{
    public function __construct(private readonly string $slug) {}

    public function resolveEndpoint(): string
    {
        return '/links/'.rawurlencode($this->slug);
    }

    public function createDtoFromResponse(Response $response): LinkData
    {
        return LinkData::fromArray($this->payload($response));
    }
}
