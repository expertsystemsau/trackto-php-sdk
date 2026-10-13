<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Resources;

use DateTimeInterface;
use ExpertSystems\TrackTo\Data\LinkAnalyticsData;
use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\LinkPage;
use ExpertSystems\TrackTo\Enums\LinkSort;
use ExpertSystems\TrackTo\Pagination\LinkIterator;
use ExpertSystems\TrackTo\Requests\CreateLinkRequest;
use ExpertSystems\TrackTo\Requests\DeleteLinkRequest;
use ExpertSystems\TrackTo\Requests\GetLinkAnalyticsRequest;
use ExpertSystems\TrackTo\Requests\GetLinkRequest;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
use ExpertSystems\TrackTo\TrackToClient;
use SensitiveParameter;

final class LinksResource
{
    public function __construct(private TrackToClient $client) {}

    public function get(string $slug): LinkData
    {
        $request = new GetLinkRequest($slug);

        return $request->createDtoFromResponse($this->client->send($request));
    }

    public function analytics(string $slug): LinkAnalyticsData
    {
        $request = new GetLinkAnalyticsRequest($slug);

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

    /**
     * @param  list<string>|null  $tags  Tag names
     */
    public function create(
        string $destinationUrl,
        ?string $slug = null,
        ?DateTimeInterface $expiresAt = null,
        #[SensitiveParameter] ?string $password = null,
        ?int $folderId = null,
        ?array $tags = null,
    ): LinkData {
        $request = new CreateLinkRequest($destinationUrl, $slug, $expiresAt, $password, $folderId, $tags);

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * Deletes the link. A missing link raises NotFoundException; it is never treated as already deleted.
     */
    public function delete(string $slug): void
    {
        $this->client->send(new DeleteLinkRequest($slug));
    }
}
