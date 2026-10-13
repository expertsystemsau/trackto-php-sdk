<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use DateTimeInterface;
use ExpertSystems\TrackTo\Data\LinkData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;
use SensitiveParameterValue;

final class CreateLinkRequest extends TrackToRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** Wrapped so a dump of the request never prints it; unwrapped only when the body is built. */
    private readonly ?SensitiveParameterValue $password;

    /**
     * @param  list<string>|null  $tags  Tag names
     */
    public function __construct(
        private readonly string $destinationUrl,
        private readonly ?string $slug = null,
        private readonly ?DateTimeInterface $expiresAt = null,
        #[SensitiveParameter] ?string $password = null,
        private readonly ?int $folderId = null,
        private readonly ?array $tags = null,
    ) {
        $this->password = $password === null ? null : new SensitiveParameterValue($password);
    }

    public function resolveEndpoint(): string
    {
        return '/links';
    }

    /**
     * Only non-null fields are sent; validation is left to the server.
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return array_filter([
            'destination_url' => $this->destinationUrl,
            'slug' => $this->slug,
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'password' => $this->password?->getValue(),
            'folder_id' => $this->folderId,
            'tags' => $this->tags,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function createDtoFromResponse(Response $response): LinkData
    {
        return $this->hydrate($response, fn (): LinkData => LinkData::fromArray($this->payload($response)));
    }
}
