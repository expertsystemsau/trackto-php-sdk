<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Resources;

use ExpertSystems\TrackTo\Data\TagData;
use ExpertSystems\TrackTo\Requests\CreateTagRequest;
use ExpertSystems\TrackTo\Requests\DeleteTagRequest;
use ExpertSystems\TrackTo\Requests\ListTagsRequest;
use ExpertSystems\TrackTo\TrackToClient;

final class TagsResource
{
    public function __construct(private TrackToClient $client) {}

    /**
     * Every tag in API order. The endpoint is not paginated.
     *
     * @return list<TagData>
     */
    public function list(): array
    {
        $request = new ListTagsRequest;

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * Creates a tag. A null colour lets the server assign one; the colour format is validated by the server.
     */
    public function create(string $name, ?string $color = null): TagData
    {
        $request = new CreateTagRequest($name, $color);

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * Deletes the tag and detaches it from its links. An unknown id raises NotFoundException.
     */
    public function delete(int $id): void
    {
        $this->client->send(new DeleteTagRequest($id));
    }
}
