<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

function lastPending(TrackToClient $client): PendingRequest
{
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);
    $client = $client->withMockClient($mock);
    TestClient::make($mock); // arms stray-request prevention
    $client->links()->get('x');

    return $mock->getLastPendingRequest() ?? throw new RuntimeException('No request recorded.');
}

dataset('blank keys', [
    'constructor empty' => [fn (): TrackToClient => new TrackToClient(apiKey: '')],
    'constructor spaces' => [fn (): TrackToClient => new TrackToClient(apiKey: '   ')],
    'withApiKey empty' => [fn (): TrackToClient => new TrackToClient(apiKey: TestClient::API_KEY)->withApiKey('')],
]);

it('rejects a blank API key with a catchable SDK exception', function (Closure $build): void {
    try {
        $build();
    } catch (ConfigurationException $e) {
        expect($e)->toBeInstanceOf(TrackToException::class)
            ->toBeInstanceOf(InvalidArgumentException::class);

        return;
    }

    $this->fail('ConfigurationException was not thrown.');
})->with('blank keys');

it('keeps the User-Agent non-blank without a trailing space for a blank suffix', function (?string $suffix): void {
    $agent = lastPending(new TrackToClient(apiKey: TestClient::API_KEY, userAgentSuffix: $suffix))
        ->headers()->get('User-Agent');

    expect($agent)->toMatch('#^trackto-php/\S+ php/\S+$#');
})->with([null, '', '   ']);

it('rejects control characters in the suffix at construction and in withUserAgentSuffix', function (string $suffix): void {
    expect(fn () => new TrackToClient(apiKey: TestClient::API_KEY, userAgentSuffix: $suffix))
        ->toThrow(ConfigurationException::class)
        ->and(fn () => (new TrackToClient(apiKey: TestClient::API_KEY))->withUserAgentSuffix($suffix))
        ->toThrow(ConfigurationException::class);
})->with(["x\r\nX-Evil: 1", "a\tb", "a\0b", "a\x7Fb"]);

it('pins the default timeouts, redirect policy and base URL', function (): void {
    $pending = lastPending(new TrackToClient(apiKey: TestClient::API_KEY));

    expect($pending->config()->get('connect_timeout'))->toBe(10.0)
        ->and($pending->config()->get('timeout'))->toBe(30.0)
        ->and($pending->config()->get('allow_redirects'))->toBeFalse()
        ->and($pending->getUri()->getScheme())->toBe('https')
        ->and($pending->getUri()->getHost())->toBe('api.track.to');
});

it('rejects a base URL that is not https, other than http on localhost', function (string $url): void {
    expect(fn () => new TrackToClient(apiKey: TestClient::API_KEY, baseUrl: $url))
        ->toThrow(ConfigurationException::class)
        ->and(fn () => (new TrackToClient(apiKey: TestClient::API_KEY))->withBaseUrl($url))
        ->toThrow(ConfigurationException::class);
})->with(['http host' => 'http://api.track.to', 'ftp' => 'ftp://api.track.to', 'no scheme' => 'api.track.to', 'relative' => '/links', 'empty' => '', 'no host' => 'https://']);

it('allows http for localhost hosts', function (string $url): void {
    expect((new TrackToClient(apiKey: TestClient::API_KEY, baseUrl: $url))->resolveBaseUrl())->toBe($url);
})->with(['http://localhost', 'http://api.localhost', 'http://127.0.0.1:8080']);

it('rejects timeouts that are zero, negative or not finite', function (float $seconds): void {
    $client = new TrackToClient(apiKey: TestClient::API_KEY);

    expect(fn () => $client->withTimeout($seconds))->toThrow(ConfigurationException::class)
        ->and(fn () => $client->withConnectTimeout($seconds))->toThrow(ConfigurationException::class)
        ->and(fn () => new TrackToClient(apiKey: TestClient::API_KEY, timeout: $seconds))->toThrow(ConfigurationException::class)
        ->and(fn () => new TrackToClient(apiKey: TestClient::API_KEY, connectTimeout: $seconds))->toThrow(ConfigurationException::class);
})->with(['zero' => 0.0, 'negative' => -1.0, 'nan' => NAN, 'infinity' => INF]);

it('rejects control characters in the version at construction and in withVersion', function (): void {
    $version = "x\r\nX-Evil: 1";

    expect(fn () => new TrackToClient(apiKey: TestClient::API_KEY, version: $version))->toThrow(ConfigurationException::class)
        ->and(fn () => (new TrackToClient(apiKey: TestClient::API_KEY))->withVersion($version))->toThrow(ConfigurationException::class);
});
