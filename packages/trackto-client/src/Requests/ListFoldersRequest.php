<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\FolderData;
use Saloon\Http\Response;

final class ListFoldersRequest extends TrackToRequest
{
    public function resolveEndpoint(): string
    {
        return '/folders';
    }

    /**
     * @return list<FolderData>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $this->hydrate($response, fn (): array => array_map(
            FolderData::fromArray(...),
            $this->dataList($response),
        ));
    }
}
