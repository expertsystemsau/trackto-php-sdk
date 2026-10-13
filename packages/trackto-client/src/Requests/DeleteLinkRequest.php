<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Support\SlugPath;
use Saloon\Enums\Method;

final class DeleteLinkRequest extends TrackToRequest
{
    protected Method $method = Method::DELETE;

    private readonly string $segment;

    public function __construct(string $slug)
    {
        $this->segment = SlugPath::encode($slug);
    }

    public function resolveEndpoint(): string
    {
        return '/links/'.$this->segment;
    }
}
