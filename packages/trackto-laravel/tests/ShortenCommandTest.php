<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\Laravel\Tests\Support\SplitOutput;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockResponse;

const SHORTEN_KEY = 'tk_shorten_secret_key';

/**
 * @return array{int, string, string} Exit code, stdout, stderr.
 */
function shortenRun(string $url): array
{
    $output = new SplitOutput;
    $code = Artisan::call('trackto:shorten', ['url' => $url], $output);

    return [$code, $output->fetch(), $output->stderr()];
}

function shortenCreated(): MockResponse
{
    return MockResponse::make(['data' => Fixtures::link()], 201);
}

function shortenInvalid(): MockResponse
{
    return Fixtures::errorResponse('validation_error', errors: ['destination_url' => ['The destination url is invalid.']]);
}

beforeEach(function (): void {
    config()->set('trackto.api_key', SHORTEN_KEY);
});

it('registers trackto:shorten', function (): void {
    expect(Artisan::all())->toHaveKey('trackto:shorten');
});

it('prints only the short URL and a newline', function (): void {
    TrackTo::fake([shortenCreated()]);

    [$code, $output] = shortenRun('https://example.com/landing');

    expect($code)->toBe(0)
        ->and($output)->toBe("https://track.to/abc123\n");
});

it('sends the argument byte for byte', function (string $url): void {
    $mock = TrackTo::fake([shortenCreated()]);

    shortenRun($url);

    $body = $mock->getLastPendingRequest()?->body()?->all();
    expect($body)->toBeArray()->and($body['destination_url'] ?? null)->toBe($url);
})->with([
    'query string' => ['https://example.com/landing?a=b&c=d'],
    'non-ASCII path' => ['https://example.com/ü?a=b&c=d'],
]);

it('exits 1 with the field errors and no short URL on a 422', function (): void {
    TrackTo::fake([shortenInvalid()]);

    [$code, $stdout, $stderr] = shortenRun('nope');

    expect($code)->toBe(1)
        ->and($stdout)->toBe('')
        ->and($stderr)->toContain('ValidationException')
        ->and($stderr)->toContain('destination_url: The destination url is invalid.')
        ->and($stderr)->not->toContain('https://track.to/')
        ->and($stderr)->not->toContain(SHORTEN_KEY);
});

it('sends a failing create exactly once', function (): void {
    $mock = TrackTo::fake([Fixtures::errorResponse('server_error')]);

    [$code] = shortenRun('https://example.com/a');

    expect($code)->toBe(1);
    $mock->assertSentCount(1);
});

it('exits 1 on a missing key without printing a URL', function (): void {
    config()->set('trackto.api_key', null);

    [$code, $stdout, $stderr] = shortenRun('https://example.com/a');

    expect($code)->toBe(1)
        ->and($stdout)->toBe('')
        ->and($stderr)->toContain('TRACKTO_API_KEY')
        ->and(app()->resolved(TrackToClient::class))->toBeFalse();
});

it('never prints the API key on success', function (): void {
    TrackTo::fake([shortenCreated()]);

    expect(shortenRun('https://example.com/a')[1])->not->toContain(SHORTEN_KEY);
});

it('leaves the shared client and its mock as they were', function (): void {
    $mock = TrackTo::fake([shortenCreated()]);
    $before = TrackTo::getFacadeRoot();

    shortenRun('https://example.com/a');

    expect(TrackTo::getFacadeRoot())->toBe($before)
        ->and(TrackTo::getFacadeRoot()->getMockClient())->toBe($mock);
});

it('sends an empty url as an empty destination_url and exits 1 on the 422', function (): void {
    $mock = TrackTo::fake([shortenInvalid()]);

    [$code] = shortenRun('');

    $body = $mock->getLastPendingRequest()?->body()?->all();
    expect($code)->toBe(1)
        ->and($body)->toBeArray()
        ->and($body['destination_url'] ?? null)->toBe('');
});

it('prints API-supplied text literally instead of reading it as console style tags', function (): void {
    $body = Fixtures::error('validation_error', '<info>boom</info>', ['<comment>url</comment>' => ['<error>bad</error>']]);
    TrackTo::fake([MockResponse::make($body, $body['statusCode'])]);

    [, , $stderr] = shortenRun('nope');

    expect($stderr)->toContain('<info>boom</info>')
        ->and($stderr)->toContain('<comment>url</comment>: <error>bad</error>');
});
