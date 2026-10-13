<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Resources;

use ExpertSystems\TrackTo\Data\FolderData;
use ExpertSystems\TrackTo\Requests\CreateFolderRequest;
use ExpertSystems\TrackTo\Requests\DeleteFolderRequest;
use ExpertSystems\TrackTo\Requests\ListFoldersRequest;
use ExpertSystems\TrackTo\TrackToClient;

final class FoldersResource
{
    public function __construct(private TrackToClient $client) {}

    /**
     * Every folder in API order. The endpoint is not paginated.
     *
     * @return list<FolderData>
     */
    public function list(): array
    {
        $request = new ListFoldersRequest;

        return $request->createDtoFromResponse($this->client->send($request));
    }

    public function create(string $name): FolderData
    {
        $request = new CreateFolderRequest($name);

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * Deletes the folder. The server refuses a folder that still holds links (ValidationException); an unknown id raises NotFoundException.
     */
    public function delete(int $id): void
    {
        $this->client->send(new DeleteFolderRequest($id));
    }
}
