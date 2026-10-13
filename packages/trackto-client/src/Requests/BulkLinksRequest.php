<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use ExpertSystems\TrackTo\Data\BulkResultData;
use ExpertSystems\TrackTo\Enums\BulkAction;
use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

final class BulkLinksRequest extends TrackToRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * One attempt, whatever withRetry() says: a bulk call is all-or-nothing, and a re-sent delete would 404 on every slug.
     * Saloon reads the request's tries before the connector's, so handleRetry is never reached.
     */
    public ?int $tries = 1;

    /**
     * @param  array<array-key, string>  $slugs  Keys are dropped so the body is always a JSON list
     * @param  array<array-key, string>  $tags  Keys are dropped so the body is always a JSON list
     */
    public function __construct(
        private readonly BulkAction $action,
        private readonly array $slugs,
        private readonly ?int $folderId = null,
        private readonly array $tags = [],
    ) {
        if (count($slugs) < 1 || count($slugs) > 100) {
            throw new InvalidArgumentException('Bulk actions take between 1 and 100 slugs.');
        }

        if ($action === BulkAction::Unknown) {
            throw new InvalidArgumentException('Unknown is not a bulk action that can be sent.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/links/bulk';
    }

    /**
     * Move always carries `folder_id` (null unfiles) and tag always carries `tags`.
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = ['action' => $this->action->value, 'slugs' => array_values($this->slugs)];

        return match ($this->action) {
            BulkAction::Move => $body + ['folder_id' => $this->folderId],
            BulkAction::Tag => $body + ['tags' => array_values($this->tags)],
            default => $body,
        };
    }

    public function createDtoFromResponse(Response $response): BulkResultData
    {
        return $this->hydrate($response, fn (): BulkResultData => BulkResultData::fromArray($this->payload($response)));
    }
}
