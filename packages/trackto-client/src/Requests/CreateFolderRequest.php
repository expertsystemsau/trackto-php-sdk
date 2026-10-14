<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\FolderData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

final class CreateFolderRequest extends TrackToRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $name) {}

    public function resolveEndpoint(): string
    {
        return '/folders';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['name' => $this->name];
    }

    public function createDtoFromResponse(Response $response): FolderData
    {
        return $this->hydrate($response, fn (): FolderData => FolderData::fromArray($this->payload($response)));
    }
}
