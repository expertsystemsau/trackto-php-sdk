<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests;

use ExpertSystems\TrackTo\Laravel\TrackToServiceProvider;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use TrackTo;

final class DiscoveryTest extends DiscoveryTestCase
{
    #[Test]
    public function it_discovers_the_package_and_sends_a_request_through_the_container_client(): void
    {
        config()->set('trackto.api_key', 'tk_discovery_key');

        $this->assertTrue(app()->providerIsLoaded(TrackToServiceProvider::class));
        $this->assertInstanceOf(TrackToClient::class, app(TrackToClient::class));
        $this->assertSame(app(TrackToClient::class), app('trackto'));
        $this->assertSame(app(TrackToClient::class), TrackTo::getFacadeRoot());

        $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

        $link = TrackTo::withMockClient($mock)->links()->get('abc123');

        $this->assertSame('https://track.to/abc123', $link->shortUrl);
        $this->assertCount(1, $mock->getRecordedResponses());

        $request = $mock->getLastPendingRequest();
        $this->assertNotNull($request);
        $this->assertSame('https://api.track.to/links/abc123', $request->getUrl());
        $this->assertSame('Bearer tk_discovery_key', $request->headers()->get('Authorization'));
        $this->assertSame('2026-07-06', $request->headers()->get('Track-Version'));
    }

    #[Test]
    public function it_registers_the_provider_once_when_it_is_also_registered_by_hand(): void
    {
        config()->set('trackto.api_key', 'tk_discovery_key');
        $client = app(TrackToClient::class);

        app()->register(TrackToServiceProvider::class);

        $this->assertCount(1, app()->getProviders(TrackToServiceProvider::class));
        $this->assertSame($client, app(TrackToClient::class));
    }
}
