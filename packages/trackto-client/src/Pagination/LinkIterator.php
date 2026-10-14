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
final readonly class LinkIterator implements IteratorAggregate
{
    public function __construct(private TrackToClient $client, private ListLinksRequest $request) {}

    /**
     * A fresh paginator per foreach, so the result can be iterated again (a bare generator cannot).
     *
     * @return Generator<int, LinkData>
     */
    public function getIterator(): Generator
    {
        foreach (new LinksPaginator($this->client, $this->request) as $response) {
            if (! $response instanceof Response) {
                continue;
            }

            foreach ($this->request->createDtoFromResponse($response)->items as $link) {
                yield $link;
            }
        }
    }
}
