<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

use ExpertSystems\TrackTo\Support\RateLimitInfo;
use LogicException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

/**
 * Any failure that came back from the API. Extends Saloon's RequestException so the retry hook sees it.
 */
abstract class ApiException extends RequestException implements TrackToException
{
    private readonly RateLimitInfo $rateLimit;

    /**
     * @param  array<string, mixed>  $errors
     */
    final public function __construct(Response $response, string $message, private readonly array $errors = [])
    {
        // An explicit message: Saloon's default embeds the raw response body.
        parent::__construct($response, $message, $response->status());

        $this->rateLimit = RateLimitInfo::fromResponse($response);
    }

    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<string>
     */
    public function errorsFor(string $field): array
    {
        $messages = $this->errors[$field] ?? null;

        return is_array($messages) ? array_values(array_filter($messages, is_string(...))) : [];
    }

    public function rateLimit(): RateLimitInfo
    {
        return $this->rateLimit;
    }

    public function response(): Response
    {
        return $this->response;
    }

    /**
     * Omits the response: its PSR request carries the Authorization header.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'status' => $this->status(),
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'rateLimit' => $this->rateLimit,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws LogicException Always
     */
    public function __serialize(): array
    {
        throw new LogicException('An API exception cannot be serialized because it holds the HTTP response.');
    }
}
