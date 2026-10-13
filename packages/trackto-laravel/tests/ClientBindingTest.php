<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    config()->set('trackto.api_key', 'tk_binding_key');
});

/**
 * @return array<string, mixed>
 */
function bindingSnapshot(TrackToClient $client): array
{
    return [
        'base_url' => $client->resolveBaseUrl(),
        'headers' => $client->headers()->all(),
        'config' => $client->config()->all(),
        'tries' => $client->tries,
        'mock' => $client->getMockClient(),
    ];
}

it('resolves one instance however it is asked for', function (): void {
    expect(app(TrackToClient::class))->toBe(app(TrackToClient::class))
        ->and(app('trackto'))->toBe(app(TrackToClient::class));
});

it('never lets a per-call key reach the next request through the singleton', function (): void {
    $client = app(TrackToClient::class);
    $mock = new MockClient([
        MockResponse::make(['data' => Fixtures::link()], 200),
        MockResponse::make(['data' => Fixtures::link()], 200),
    ]);

    TrackTo::withApiKey('tk_other')->withMockClient($mock)->links()->get('abc123');
    TrackTo::withMockClient($mock)->links()->get('abc123');

    $authorization = array_map(
        static fn ($response): ?string => $response->getPendingRequest()->headers()->get('Authorization'),
        $mock->getRecordedResponses(),
    );

    expect($authorization)->toBe(['Bearer tk_other', 'Bearer tk_binding_key'])
        ->and(TrackTo::getFacadeRoot())->toBe($client);
});

dataset('with methods', [
    'withApiKey' => [fn (TrackToClient $c): TrackToClient => $c->withApiKey('tk_copy')],
    'withBaseUrl' => [fn (TrackToClient $c): TrackToClient => $c->withBaseUrl('https://staging.example.test')],
    'withVersion' => [fn (TrackToClient $c): TrackToClient => $c->withVersion(null)],
    'withConnectTimeout' => [fn (TrackToClient $c): TrackToClient => $c->withConnectTimeout(1.5)],
    'withTimeout' => [fn (TrackToClient $c): TrackToClient => $c->withTimeout(2.5)],
    'withUserAgentSuffix' => [fn (TrackToClient $c): TrackToClient => $c->withUserAgentSuffix('copy/1.0')],
    'withMockClient' => [fn (TrackToClient $c): TrackToClient => $c->withMockClient(new MockClient)],
    'withRetry' => [fn (TrackToClient $c): TrackToClient => $c->withRetry(5, 1.0)],
    'withoutRetry' => [fn (TrackToClient $c): TrackToClient => $c->withoutRetry()],
]);

it('returns a new instance from every with*() and leaves the singleton as configured', function (Closure $with): void {
    $client = app(TrackToClient::class);
    $before = bindingSnapshot($client);

    $copy = $with($client);

    expect($copy)->not->toBe($client)
        ->and(bindingSnapshot($client))->toEqual($before)
        ->and(app(TrackToClient::class))->toBe($client);
})->with('with methods');

it('keeps the resolved singleton when trackto config changes after the first resolve', function (): void {
    $client = app(TrackToClient::class);
    $baseUrl = $client->resolveBaseUrl();

    config()->set('trackto.base_url', 'https://changed.example');

    expect(app(TrackToClient::class))->toBe($client)
        ->and(app(TrackToClient::class)->resolveBaseUrl())->toBe($baseUrl);
});
