<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Pagination;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\LinkPage;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
use LogicException;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\PagedPaginator;

/**
 * Pages are always requested as `?page=N`; no URL from a response body is ever read or followed.
 */
final class LinksPaginator extends PagedPaginator
{
    /**
     * Hard ceiling so a server that never reports the last page cannot walk forever.
     */
    protected ?int $maxPages = 10_000;

    protected function applyPagination(Request $request): Request
    {
        // Saloon's currentPage is 0-based; its `$page` counter is deprecated.
        $request->query()->add('page', $this->currentPage + 1);

        return $request;
    }

    protected function isLastPage(Response $response): bool
    {
        $page = $this->hydrate($response);

        // Our own counter, not meta.current_page: a stale or constant value from the server must not extend
        // the walk. Saloon calls this after next(), so currentPage already equals the 1-based page just read.
        return $page->items === [] || $this->currentPage >= $page->lastPage;
    }

    /**
     * @return list<LinkData>
     */
    protected function getPageItems(Response $response, Request $request): array
    {
        return $this->hydrate($response)->items;
    }

    private function hydrate(Response $response): LinkPage
    {
        $request = $this->request;

        if (! $request instanceof ListLinksRequest) {
            throw new LogicException('LinksPaginator only paginates ListLinksRequest.');
        }

        return $request->createDtoFromResponse($response);
    }
}
