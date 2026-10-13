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
use WeakMap;

/**
 * Pages are always requested as `?page=N`; no URL from a response body is ever read or followed.
 */
final class LinksPaginator extends PagedPaginator
{
    /**
     * Hard ceiling so a server that never reports the last page cannot walk forever.
     */
    protected ?int $maxPages = 10_000;

    /** @internal Counts cache misses; tests observe single hydration through it. */
    public int $hydrations = 0;

    /**
     * Held here, not on the request, because Saloon clones the request for every page.
     *
     * @var WeakMap<Response, LinkPage>|null
     */
    private ?WeakMap $pages = null;

    /**
     * Saloon reads each response several times (page items, last-page check); hydrate it once.
     */
    public function page(Response $response): LinkPage
    {
        $this->pages ??= new WeakMap;

        if (! isset($this->pages[$response])) {
            $this->pages[$response] = $this->hydrate($response);
            $this->hydrations++;
        }

        return $this->pages[$response];
    }

    protected function applyPagination(Request $request): Request
    {
        // Saloon's currentPage is 0-based; its `$page` counter is deprecated.
        $request->query()->add('page', $this->currentPage + 1);

        return $request;
    }

    protected function isLastPage(Response $response): bool
    {
        $page = $this->page($response);

        // Our own counter, not meta.current_page: a stale or constant value from the server must not extend
        // the walk. Saloon calls this after next(), so currentPage already equals the 1-based page just read.
        return $page->items === [] || $this->currentPage >= $page->lastPage;
    }

    /**
     * @return list<LinkData>
     */
    protected function getPageItems(Response $response, Request $request): array
    {
        return $this->page($response)->items;
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
