<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Resources;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Requests\GetLinkRequest;
use ExpertSystems\TrackTo\TrackToClient;

final class LinksResource
{
    public function __construct(private TrackToClient $client) {}

    public function get(string $slug): LinkData
    {
        $request = new GetLinkRequest($slug);

        return $request->createDtoFromResponse($this->client->send($request));
    }
}
