<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\Laravel\Tests\Support\SplitOutput;
use ExpertSystems\TrackTo\Requests\ListLinksRequest;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Artisan;
use Saloon\Exceptions\NoMockResponseFoundException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

const PING_KEY = 'tk_ping_secret_key';

/**
 * @return array{int, string, string} Exit code, stdout, stderr.
 */
function pingRun(): array
{
    $output = new SplitOutput;
    $code = Artisan::call('trackto:ping', [], $output);

    return [$code, $output->fetch(), $output->stderr()];
}

beforeEach(function (): void {
    config()->set('trackto.api_key', PING_KEY);
});

it('registers trackto:ping', function (): void {
    expect(Artisan::all())->toHaveKey('trackto:ping');
});

it('exits 0 and prints the base URL and API version when the key is accepted', function (array $links): void {
    TrackTo::fake([MockResponse::make(Fixtures::page($links), 200)]);

    [$code, $output] = pingRun();

    expect($code)->toBe(0)
        ->and($output)->toContain('https://api.track.to')
        ->and($output)->toContain('2026-07-06')
        ->and($output)->not->toContain(PING_KEY);
})->with([
    'one link' => [[Fixtures::link()]],
    'empty page' => [[]],
]);

it('prints none when the client sends no Track-Version', function (): void {
    TrackTo::fake([MockResponse::make(Fixtures::page(), 200)]);
    TrackTo::swap(TrackTo::getFacadeRoot()->withVersion(null));

    [$code, $output] = pingRun();

    expect($code)->toBe(0)->and($output)->toContain('none');
});

it('exits 1 and names the exception class on every SDK failure', function (Closure $response, string $class): void {
    TrackTo::fake([$response()]);
    TrackTo::swap(TrackTo::getFacadeRoot()->withoutRetry());

    [$code, $stdout, $stderr] = pingRun();

    expect($code)->toBe(1)
        ->and($stdout)->toBe('')
        ->and($stderr)->toContain($class)
        ->and($stderr)->not->toContain(PING_KEY);
})->with([
    'unauthorized' => [fn () => Fixtures::errorResponse('unauthorized'), 'UnauthorizedException'],
    'team suspended' => [fn () => Fixtures::errorResponse('team_suspended'), 'TeamSuspendedException'],
    'dropped connection' => [
        fn () => MockResponse::make()->throw(fn (PendingRequest $p) => new FatalRequestException(
            new ConnectException('cURL error 7: Failed to connect', $p->createPsrRequest()),
            $p,
        )),
        'ConnectionException',
    ],
]);

it('exits 1 on a missing key without ever building the client', function (): void {
    config()->set('trackto.api_key', null);

    [$code, $stdout, $stderr] = pingRun();

    expect($code)->toBe(1)
        ->and($stdout)->toBe('')
        ->and($stderr)->toContain('ConfigurationException')
        ->and($stderr)->toContain('TRACKTO_API_KEY')
        ->and(app()->resolved(TrackToClient::class))->toBeFalse();
});

it('does not catch non-SDK throwables', function (): void {
    TrackTo::fake();

    expect(fn () => Artisan::call('trackto:ping'))->toThrow(NoMockResponseFoundException::class);
});

it('leaves the shared client and its mock as they were', function (): void {
    $mock = TrackTo::fake([MockResponse::make(Fixtures::page(), 200)]);
    $before = TrackTo::getFacadeRoot();

    pingRun();

    expect(TrackTo::getFacadeRoot())->toBe($before)
        ->and(TrackTo::getFacadeRoot()->getMockClient())->toBe($mock);
});

it('sends exactly one GET /links with per_page 1', function (): void {
    $mock = TrackTo::fake([MockResponse::make(Fixtures::page(), 200)]);

    pingRun();

    $mock->assertSentCount(1);
    $mock->assertSent(ListLinksRequest::class);

    $request = $mock->getLastPendingRequest() ?? throw new RuntimeException('No request recorded.');
    expect($request->getMethod()->value)->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/links')
        ->and($request->query()->all())->toMatchArray(['per_page' => 1]);
});

it('retries a GET 503 and exits 0 when the next attempt succeeds', function (): void {
    $mock = TrackTo::fake([
        Fixtures::errorResponse('server_error', status: 503),
        MockResponse::make(Fixtures::page(), 200),
    ]);

    [$code] = pingRun();

    expect($code)->toBe(0);
    $mock->assertSentCount(2);
});
