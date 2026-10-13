<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\LinkData;
use InvalidArgumentException;
use Saloon\Http\Response;

final class GetLinkRequest extends TrackToRequest
{
    public function __construct(private readonly string $slug)
    {
        // A blank slug would turn GET /links/{slug} into GET /links/, the list endpoint.
        if (trim($slug) === '') {
            throw new InvalidArgumentException('The link slug must not be blank.');
        }
    }

    public function resolveEndpoint(): string
    {
        // rawurlencode leaves dots alone; encode them so `.` and `..` never become dot segments.
        return '/links/'.str_replace('.', '%2E', rawurlencode($this->slug));
    }

    public function createDtoFromResponse(Response $response): LinkData
    {
        return LinkData::fromArray($this->payload($response));
    }
}
