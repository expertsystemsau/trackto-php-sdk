<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\Laravel\Tests\Support\LinkCreator;
use ExpertSystems\TrackTo\Requests\CreateLinkRequest;
use ExpertSystems\TrackTo\Requests\GetLinkRequest;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Exceptions\NoMockResponseFoundException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

function fakeLink(string $slug = 'abc123'): MockResponse
{
    return MockResponse::make(['data' => Fixtures::link(['slug' => $slug])], 200);
}

function fakeLastRequest(MockClient $mock): PendingRequest
{
    return $mock->getLastPendingRequest() ?? throw new RuntimeException('No request recorded.');
}

it('returns the mock, and the facade, the container and constructor injection all hold the faked client', function (): void {
    $mock = TrackTo::fake([MockResponse::make(['data' => Fixtures::link()], 201)]);

    $link = app(LinkCreator::class)->create('https://example.com/a');

    expect($mock)->toBeInstanceOf(MockClient::class)
        ->and(TrackTo::getFacadeRoot()->getMockClient())->toBe($mock)
        ->and(app(TrackToClient::class))->toBe(TrackTo::getFacadeRoot())
        ->and(app('trackto'))->toBe(TrackTo::getFacadeRoot())
        ->and($link)->toBeInstanceOf(LinkData::class)
        ->and(fakeLastRequest($mock)->body()?->all())->toMatchArray(['destination_url' => 'https://example.com/a']);

    $mock->assertSent(CreateLinkRequest::class);
    $mock->assertSentCount(1);
});

it('routes facade calls to the mock', function (): void {
    $mock = TrackTo::fake([fakeLink()]);

    TrackTo::links()->create(destinationUrl: 'https://example.com/b');

    $mock->assertSent(CreateLinkRequest::class);
    $mock->assertSentCount(1);
});

it('throws on the first call when faked with no responses, and sends nothing', function (): void {
    $mock = TrackTo::fake();

    expect(fn () => TrackTo::links()->get('abc123'))->toThrow(NoMockResponseFoundException::class);

    $mock->assertNothingSent();
});

it('fakes without a configured API key and sends the placeholder key', function (): void {
    config()->set('trackto.api_key', null);

    $mock = TrackTo::fake([fakeLink()]);
    TrackTo::links()->get('abc123');

    expect(fakeLastRequest($mock)->headers()->get('Authorization'))->toBe('Bearer fake-api-key');
});

it('uses the configured key and base URL when set', function (): void {
    config()->set('trackto.api_key', 'tk_fake_configured');
    config()->set('trackto.base_url', 'https://api.example.test');

    $mock = TrackTo::fake([fakeLink()]);
    TrackTo::links()->get('abc123');

    $request = fakeLastRequest($mock);

    expect($request->headers()->get('Authorization'))->toBe('Bearer tk_fake_configured')
        ->and($request->getUri()->getHost())->toBe('api.example.test');
});

it('keeps routing a with*() copy to the mock', function (): void {
    $mock = TrackTo::fake([fakeLink()]);

    TrackTo::withApiKey('tk_copy')->links()->get('abc123');

    $mock->assertSentCount(1);
    expect(fakeLastRequest($mock)->headers()->get('Authorization'))->toBe('Bearer tk_copy');
});

it('routes every later request to the second mock when faked twice', function (): void {
    $first = TrackTo::fake([fakeLink()]);
    $second = TrackTo::fake([fakeLink()]);

    TrackTo::links()->get('abc123');

    $second->assertSentCount(1);
    $first->assertNothingSent();
});

it('does not report as a Laravel fake and installs no global mock', function (): void {
    TrackTo::fake();

    expect(TrackTo::isFake())->toBeFalse()
        ->and(MockClient::getGlobal())->toBeNull();
});

it('leaves an object that stored the client before fake() with the real client', function (): void {
    config()->set('trackto.api_key', 'tk_before_fake');
    $before = app(TrackToClient::class);

    $mock = TrackTo::fake();

    expect($before->getMockClient())->toBeNull()
        ->and(TrackTo::getFacadeRoot())->not->toBe($before)
        ->and(TrackTo::getFacadeRoot()->getMockClient())->toBe($mock);
});

it('consumes a sequence in order', function (): void {
    TrackTo::fake([fakeLink('first'), fakeLink('second')]);

    expect(TrackTo::links()->get('x')->slug)->toBe('first')
        ->and(TrackTo::links()->get('x')->slug)->toBe('second');
});

it('matches a request-class map by class regardless of call order', function (): void {
    TrackTo::fake([
        GetLinkRequest::class => fakeLink('by-get'),
        ListLinksRequest::class => MockResponse::make(Fixtures::page([Fixtures::link(['slug' => 'by-list'])]), 200),
    ]);

    $page = TrackTo::links()->list();
    $link = TrackTo::links()->get('anything');

    expect($link->slug)->toBe('by-get')
        ->and($page->items[0]->slug)->toBe('by-list');
});
