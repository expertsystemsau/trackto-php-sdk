<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Tests\Support;

use GuzzleHttp\Exception\ConnectException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class Mocks
{
    /**
     * A connection failure mock that counts how often it is attempted (MockClient does not record thrown mocks).
     */
    public static function drop(int &$attempts): MockResponse
    {
        return MockResponse::make()->throw(static function (PendingRequest $pending) use (&$attempts): FatalRequestException {
            $attempts++;

            return new FatalRequestException(new ConnectException('cURL error 7: Failed to connect', $pending->createPsrRequest()), $pending);
        });
    }
}
