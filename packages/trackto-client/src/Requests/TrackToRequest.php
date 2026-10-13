<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use JsonException;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use UnexpectedValueException;

abstract class TrackToRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * The response's `data` object. Saloon's json() is avoided: it throws TypeError on scalar JSON.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    protected function payload(Response $response): array
    {
        $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        if (is_array($body) && is_array($body['data'] ?? null)) {
            /** @var array<string, mixed> */
            return $body['data'];
        }

        throw new UnexpectedValueException('The track.to response has no data object.');
    }
}
