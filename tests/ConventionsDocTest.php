<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\ErrorMapper;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Resources\FoldersResource;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Resources\TagsResource;
use ExpertSystems\TrackTo\Support\RateLimitInfo;
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

/**
 * The `$name` parameters, in order, of the doc's `public function <method>(` block.
 *
 * @return list<string>
 */
function conventionsSignature(string $method): array
{
    $doc = conventionsDoc();
    $start = strpos($doc, "public function {$method}(");

    if ($start === false) {
        return [];
    }

    $end = strpos($doc, '):', $start);
    preg_match_all('/\$(\w+)/', substr($doc, $start, ($end === false ? strlen($doc) : $end) - $start), $matches);

    return $matches[1];
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
        'Updates',
        'Bulk actions',
        'Analytics',
        'Folders and tags',
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

    expect(conventionsDoc())->toContain('Exceptions\InvalidArgumentException');

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
    'links update' => [LinksResource::class, 'update', []],
    'links delete' => [LinksResource::class, 'delete', []],
    'links analytics' => [LinksResource::class, 'analytics', []],
    'links bulkMove' => [LinksResource::class, 'bulkMove', []],
    'links bulkTag' => [LinksResource::class, 'bulkTag', []],
    'links bulkDelete' => [LinksResource::class, 'bulkDelete', []],
    'folders create' => [FoldersResource::class, 'create', []],
    'folders delete' => [FoldersResource::class, 'delete', []],
    'tags create' => [TagsResource::class, 'create', []],
    'tags delete' => [TagsResource::class, 'delete', []],
]);

it('pins the update and bulk signatures in order', function (string $method): void {
    expect(conventionsSignature($method))->toBe(conventionsParameters(new ReflectionMethod(LinksResource::class, $method)));
})->with(['update', 'bulkMove', 'bulkTag', 'bulkDelete']);

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
        ->toContain('`tags: []`')
        ->not->toContain('provisional')
        ->not->toContain('Phase 12');
});

it('records the bulk promises', function (): void {
    expect(conventionsDoc())
        ->toContain('between 1 and 100 slugs')
        ->toContain('never chunked')
        ->toContain('never retried')
        ->toContain('changes nothing')
        ->toContain('partial results')
        ->toContain('re-list');
});

it('records the remaining operations', function (): void {
    expect(conventionsDoc())
        ->toContain('analytics(')
        ->toContain('folders()')
        ->toContain('tags()')
        ->toContain('unpaginated')
        ->toContain('10,000 pages')
        ->toContain('`headers()` and `config()`')
        ->toContain('no-op')
        ->toContain('inspect `response()` before retrying')
        ->toContain('`folder()`')
        ->toContain('`tag()`')
        ->toContain('`analytics()`')
        ->toContain('`bulk()`');
});

it('records the accepted exception dump residual', function (): void {
    expect(conventionsDoc())
        ->toContain('var_export')
        ->toContain('dd(')
        ->toContain('Authorization')
        ->toContain('log `getMessage()` and `status()`');
});

it('states that a 429 reset timing is honoured only when the RateLimit header shows no budget left', function (): void {
    expect(conventionsDoc())->toContain('`RateLimit` header showing no budget left')
        ->and(RateLimitInfo::fromHeaders(['RateLimit' => '"default";r=3;t=1'])->retryAfter)->toBeNull()
        ->and(RateLimitInfo::fromHeaders(['RateLimit' => '"default";r=0;t=1'])->retryAfter)->toBe(1);
});
