<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use GuzzleHttp\Exception\ConnectException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

it('turns a dropped connection into a ConnectionException with no previous', function (): void {
    $mock = new MockClient([
        MockResponse::make()->throw(fn (PendingRequest $p) => new FatalRequestException(
            new ConnectException('cURL error 7: Failed to connect', $p->createPsrRequest()),
            $p,
        )),
    ]);

    try {
        TestClient::make($mock)->links()->get('x');
    } catch (TrackToException $e) {
        expect($e)->toBeInstanceOf(ConnectionException::class)
            ->and($e->getPrevious())->toBeNull()
            ->and($e->getMessage())->toContain('cURL error 7')
            ->and($e->getMessage())->not->toContain(TestClient::API_KEY);

        return;
    }

    $this->fail('Expected a ConnectionException.');
});
