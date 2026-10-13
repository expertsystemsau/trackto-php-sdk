<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Requests;

use Exception;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use JsonException;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use TypeError;
use UnexpectedValueException;
use ValueError;

abstract class TrackToRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * The whole decoded JSON object. Saloon's json() is avoided: it throws TypeError on scalar JSON.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    protected function decode(Response $response): array
    {
        $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($body) || array_is_list($body) && $body !== []) {
            throw new UnexpectedValueException('The track.to response is not a JSON object.');
        }

        return $body;
    }

    /**
     * The response's `data` object.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    protected function payload(Response $response): array
    {
        $data = $this->decode($response)['data'] ?? null;

        if (is_array($data)) {
            /** @var array<string, mixed> */
            return $data;
        }

        throw new UnexpectedValueException('The track.to response has no data object.');
    }

    /**
     * Runs a hydration closure, turning any failure to read a 2xx body into UnexpectedResponseException.
     *
     * @template T
     *
     * @param  callable(): T  $build
     * @return T
     */
    final protected function hydrate(Response $response, callable $build): mixed
    {
        try {
            return $build();
        } catch (TypeError|ValueError|Exception) {
            throw new UnexpectedResponseException(
                $response,
                sprintf('Unexpected response body from the track.to API (HTTP %d).', $response->status()),
            );
        }
    }
}
