<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use Saloon\Http\Faking\MockClient;

// A and B share no state but the process; Pest's random order (phpunit.xml.dist) runs them in either order.
it('holds the mock after faking, with no global mock', function (): void {
    $mock = TrackTo::fake();

    expect(TrackTo::getFacadeRoot()->getMockClient())->toBe($mock)
        ->and(MockClient::getGlobal())->toBeNull();
});

it('gets a client with no mock when the test does not fake, whatever ran before', function (): void {
    config()->set('trackto.api_key', 'tk_leak_key');

    expect(TrackTo::getFacadeRoot()->getMockClient())->toBeNull()
        ->and(MockClient::getGlobal())->toBeNull();
});

it('drops the mock when the application is refreshed', function (): void {
    TrackTo::fake();
    expect(TrackTo::getFacadeRoot()->getMockClient())->not->toBeNull();

    $this->refreshApplication();
    config()->set('trackto.api_key', 'tk_leak_key');

    expect(TrackTo::getFacadeRoot()->getMockClient())->toBeNull()
        ->and(MockClient::getGlobal())->toBeNull();
});
