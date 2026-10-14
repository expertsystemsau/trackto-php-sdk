<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\TagData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

final class CreateTagRequest extends TrackToRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $name,
        private readonly ?string $color = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/tags';
    }

    /**
     * A null colour is left out so the server picks one.
     *
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return array_filter(['name' => $this->name, 'color' => $this->color], static fn (?string $value): bool => $value !== null);
    }

    public function createDtoFromResponse(Response $response): TagData
    {
        return $this->hydrate($response, fn (): TagData => TagData::fromArray($this->payload($response)));
    }
}
