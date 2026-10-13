<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Foundation\Application;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * Runs one simulated Octane worker iteration: the booted application is cloned for the "request" and the
 * clone is handed to the callback.
 *
 * Mirrors track.to's handleOctaneRequest() and leaves out the same step, Octane's CurrentApplication::set():
 * swapping the container and facade root under PHPUnit crashes the process. The facade root therefore stays
 * on the base application, so callers resolve through $sandbox->make() and check that the warm instance is
 * the facade root.
 *
 * @param  Closure(Application): void  $handle
 */
function octaneWorkerIteration(Closure $handle): void
{
    /** @var Application $sandbox */
    $sandbox = clone app();

    $handle($sandbox);
}

/**
 * @return list<string|null>
 */
function octaneAuthorizationHeaders(MockClient $mock): array
{
    return array_map(
        static fn ($response): ?string => $response->getPendingRequest()->headers()->get('Authorization'),
        $mock->getRecordedResponses(),
    );
}

function octaneMock(): MockClient
{
    return new MockClient([
        MockResponse::make(['data' => Fixtures::link()], 200),
        MockResponse::make(['data' => Fixtures::link()], 200),
    ]);
}

it('does not carry a per-call key into the next worker iteration on the warm instance', function (): void {
    config()->set('trackto.api_key', 'tk_configured');
    $base = app(TrackToClient::class);
    $mock = octaneMock();

    octaneWorkerIteration(function (Application $sandbox) use ($base, $mock): void {
        expect($sandbox->make(TrackToClient::class))->toBe($base)
            ->and(TrackTo::getFacadeRoot())->toBe($base);

        TrackTo::withApiKey('tk_other')->withMockClient($mock)->links()->get('abc123');
    });

    octaneWorkerIteration(function (Application $sandbox) use ($base, $mock): void {
        expect($sandbox->make(TrackToClient::class))->toBe($base);

        $sandbox->make(TrackToClient::class)->withMockClient($mock)->links()->get('abc123');
    });

    expect(octaneAuthorizationHeaders($mock))->toBe(['Bearer tk_other', 'Bearer tk_configured']);
});

it('builds a separate client per iteration on the cold path and never resolves one on the base app', function (): void {
    config()->set('trackto.api_key', 'tk_configured');
    $mock = octaneMock();
    $instances = [];

    foreach ([1, 2] as $_) {
        octaneWorkerIteration(function (Application $sandbox) use ($mock, &$instances): void {
            $instances[] = $client = $sandbox->make(TrackToClient::class);

            $client->withMockClient($mock)->links()->get('abc123');
        });
    }

    expect($instances[0])->not->toBe($instances[1])
        ->and(octaneAuthorizationHeaders($mock))->toBe(['Bearer tk_configured', 'Bearer tk_configured'])
        ->and(app()->resolved(TrackToClient::class))->toBeFalse();
});
