<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\TagData;
use Saloon\Http\Response;

final class ListTagsRequest extends TrackToRequest
{
    public function resolveEndpoint(): string
    {
        return '/tags';
    }

    /**
     * @return list<TagData>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $this->hydrate($response, fn (): array => array_map(
            TagData::fromArray(...),
            $this->dataList($response),
        ));
    }
}
