<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use Saloon\Enums\Method;

final class DeleteFolderRequest extends TrackToRequest
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly int $id) {}

    public function resolveEndpoint(): string
    {
        return '/folders/'.$this->id;
    }
}
