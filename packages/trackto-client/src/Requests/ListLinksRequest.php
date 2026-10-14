<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\LinkPage;
use ExpertSystems\TrackTo\Enums\LinkSort;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

final class ListLinksRequest extends TrackToRequest implements Paginatable
{
    /**
     * `per_page` is not checked here: the server clamps it to 1-100.
     */
    public function __construct(
        private readonly ?string $search = null,
        private readonly ?int $folderId = null,
        private readonly ?int $tag = null,
        private readonly LinkSort $sort = LinkSort::Recent,
        private readonly ?int $perPage = null,
        private readonly ?int $page = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/links';
    }

    /**
     * @return array<string, scalar>
     */
    protected function defaultQuery(): array
    {
        return array_filter([
            'q' => $this->search,
            'folder' => $this->folderId,
            'tag' => $this->tag,
            'sort' => $this->sort->value,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ], static fn (string|int|null $value): bool => $value !== null);
    }

    public function createDtoFromResponse(Response $response): LinkPage
    {
        return $this->hydrate($response, fn (): LinkPage => LinkPage::fromArray($this->decode($response)));
    }
}
