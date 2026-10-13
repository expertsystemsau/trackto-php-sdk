<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Resources;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\LinkPage;
use ExpertSystems\TrackTo\Enums\LinkSort;
use ExpertSystems\TrackTo\Pagination\LinkIterator;
use ExpertSystems\TrackTo\Requests\GetLinkRequest;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
use ExpertSystems\TrackTo\TrackToClient;

final class LinksResource
{
    public function __construct(private TrackToClient $client) {}

    public function get(string $slug): LinkData
    {
        $request = new GetLinkRequest($slug);

        return $request->createDtoFromResponse($this->client->send($request));
    }

    public function list(
        ?string $search = null,
        ?int $folderId = null,
        ?int $tag = null,
        LinkSort $sort = LinkSort::Recent,
        ?int $perPage = null,
        ?int $page = null,
    ): LinkPage {
        $request = new ListLinksRequest($search, $folderId, $tag, $sort, $perPage, $page);

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * Lazily yields every link across all pages, in API order. Each foreach sends a fresh set of requests.
     */
    public function all(
        ?string $search = null,
        ?int $folderId = null,
        ?int $tag = null,
        LinkSort $sort = LinkSort::Recent,
        ?int $perPage = null,
    ): LinkIterator {
        return new LinkIterator(
            $this->client,
            new ListLinksRequest($search, $folderId, $tag, $sort, $perPage, null),
        );
    }
}
