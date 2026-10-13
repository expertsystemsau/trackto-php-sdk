<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use DateTimeInterface;
use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Support\Omitted;
use ExpertSystems\TrackTo\Support\SlugPath;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class UpdateLinkRequest extends TrackToRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    private readonly string $segment;

    /**
     * @param  array<array-key, string>|Omitted  $tags  Tag names; [] removes all. Keys are dropped so the body is always a JSON list
     */
    public function __construct(
        string $slug,
        private readonly string $destinationUrl,
        private readonly string|Omitted $newSlug = Omitted::Value,
        private readonly DateTimeInterface|Omitted|null $expiresAt = Omitted::Value,
        private readonly int|Omitted|null $folderId = Omitted::Value,
        private readonly array|Omitted $tags = Omitted::Value,
        #[SensitiveParameter] private readonly ?string $password = null,
        private readonly bool $removePassword = false,
    ) {
        $this->segment = SlugPath::encode($slug);
    }

    public function resolveEndpoint(): string
    {
        return '/links/'.$this->segment;
    }

    /**
     * A key is present only for an argument the caller passed. `null` and `[]` are kept: they clear the field.
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = ['destination_url' => $this->destinationUrl];

        if ($this->newSlug !== Omitted::Value) {
            $body['slug'] = $this->newSlug;
        }

        if ($this->expiresAt !== Omitted::Value) {
            $body['expires_at'] = $this->expiresAt instanceof DateTimeInterface
                ? $this->expiresAt->format(DATE_ATOM)
                : null;
        }

        if ($this->folderId !== Omitted::Value) {
            $body['folder_id'] = $this->folderId;
        }

        if ($this->tags !== Omitted::Value) {
            $body['tags'] = array_values($this->tags);
        }

        if (is_string($this->password)) {
            $body['password'] = $this->password;
        }

        if ($this->removePassword) {
            $body['remove_password'] = true;
        }

        return $body;
    }

    public function createDtoFromResponse(Response $response): LinkData
    {
        return $this->hydrate($response, fn (): LinkData => LinkData::fromArray($this->payload($response)));
    }
}
