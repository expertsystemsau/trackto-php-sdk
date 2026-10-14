<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Pagination;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
use ExpertSystems\TrackTo\TrackToClient;
use Generator;
use IteratorAggregate;
use Saloon\Http\Response;

/**
 * @implements IteratorAggregate<int, LinkData>
 */
final class LinkIterator implements IteratorAggregate
{
    public function __construct(private readonly TrackToClient $client, private readonly ListLinksRequest $request) {}

    /**
     * A fresh paginator per foreach, so the result can be iterated again (a bare generator cannot).
     *
     * @return Generator<int, LinkData>
     */
    public function getIterator(): Generator
    {
        $paginator = new LinksPaginator($this->client, $this->request);

        // Saloon's items() is typed as yielding responses, so walk the pages and read items through the cached page().
        // Async pagination is never enabled on this paginator, so every element is a Response (Saloon's own items() narrows the same way).
        /** @var Response $response */
        foreach ($paginator as $response) {
            foreach ($paginator->page($response)->items as $link) {
                yield $link;
            }
        }
    }
}
