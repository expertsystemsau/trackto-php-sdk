<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\ErrorMapper;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\TrackToClient;

function conventionsDoc(): string
{
    return (string) file_get_contents(dirname(__DIR__).'/docs/conventions.md');
}

/**
 * @param  list<string>  $except
 * @return list<string>
 */
function conventionsParameters(ReflectionMethod $method, array $except = []): array
{
    return array_values(array_diff(array_map(static fn (ReflectionParameter $p): string => $p->getName(), $method->getParameters()), $except));
}

function conventionsDefault(ReflectionMethod $method, string $parameter): mixed
{
    foreach ($method->getParameters() as $candidate) {
        if ($candidate->getName() === $parameter) {
            return $candidate->getDefaultValue();
        }
    }

    throw new LogicException("No parameter {$parameter} on {$method->getName()}().");
}

it('has every section in order', function (): void {
    preg_match_all('/^## (.+)$/m', conventionsDoc(), $matches);

    expect($matches[1])->toBe([
        'Client shape',
        'Resources and methods',
        'Data objects',
        'Exceptions',
        'Updates (Phase 12)',
        'Retry',
        'Timeouts and transport',
        'Testing',
        'Security notes',
        'Contract harness',
    ]);
});

it('names every exception class', function (): void {
    $classes = [...array_values(ErrorMapper::BY_NAME), TrackToException::class, ConnectionException::class, UnexpectedResponseException::class, ConfigurationException::class];

    foreach ($classes as $class) {
        expect(conventionsDoc())->toContain((new ReflectionClass($class))->getShortName());
    }

    foreach (array_keys(ErrorMapper::BY_NAME) as $code) {
        expect(conventionsDoc())->toContain('`'.$code.'`');
    }
});

it('shows every parameter as a named argument', function (string $class, string $method, array $except): void {
    foreach (conventionsParameters(new ReflectionMethod($class, $method), $except) as $name) {
        expect(conventionsDoc())->toContain($name.':');
    }
})->with([
    'client constructor' => [TrackToClient::class, '__construct', ['sleeper']],
    'links list' => [LinksResource::class, 'list', []],
    'links all' => [LinksResource::class, 'all', []],
    'links create' => [LinksResource::class, 'create', []],
]);

it('lists every with method the client declares', function (): void {
    $declared = array_filter(
        (new ReflectionClass(TrackToClient::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn (ReflectionMethod $m): bool => str_starts_with($m->getName(), 'with') && $m->getDeclaringClass()->getName() === TrackToClient::class,
    );

    expect($declared)->not->toBeEmpty();

    foreach ($declared as $method) {
        expect(conventionsDoc())->toContain('`'.$method->getName().'()`');
    }
});

it('states the timeout and retry defaults the code ships', function (): void {
    $constructor = new ReflectionMethod(TrackToClient::class, '__construct');
    $connect = (int) conventionsDefault($constructor, 'connectTimeout');
    $timeout = (int) conventionsDefault($constructor, 'timeout');
    $maxWait = (int) conventionsDefault(new ReflectionMethod(TrackToClient::class, 'withRetry'), 'maxWait');
    $tries = (new TrackToClient(apiKey: 'conventions-key'))->tries;

    expect(conventionsDoc())
        ->toContain("connect timeout of {$connect} seconds")
        ->toContain("request timeout of {$timeout} seconds")
        ->toContain("{$maxWait}-second cap")
        ->toContain("{$tries} attempts")
        ->toContain('https://'.parse_url(TrackToClient::DEFAULT_BASE_URL, PHP_URL_HOST))
        ->toContain(TrackToClient::DEFAULT_VERSION);
});

it('records the update semantics', function (): void {
    expect(conventionsDoc())
        ->toContain('Omitted')
        ->toContain('removePassword: true')
        ->toContain('`null` means clear')
        ->toContain('unchanged')
        ->toContain('provisional');
});

it('records the accepted exception dump residual', function (): void {
    expect(conventionsDoc())
        ->toContain('var_export')
        ->toContain('dd(')
        ->toContain('Authorization')
        ->toContain('log `getMessage()` and `status()`');
});
