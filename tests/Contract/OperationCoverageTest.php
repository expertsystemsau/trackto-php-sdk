<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;

$excluded = require __DIR__.'/excluded-operations.php';

it('builds or excludes every spec operation', function () use ($excluded): void {
    expect(Checks::operations(Checks::spec(), Checks::requestOperations(), $excluded))->toBe([]);
});

it('reads the built operations from the request classes', function (): void {
    $built = Checks::requestOperations();
    sort($built);

    expect($built)->toBe(['GET /links', 'GET /links/{}', 'POST /links']);
});

it('excludes exactly the ten Phase 12 operations', function () use ($excluded): void {
    expect($excluded)->toHaveCount(10)->and($excluded)->toEqualCanonicalizing([
        'POST /links/bulk',
        'GET /folders',
        'POST /folders',
        'DELETE /folders/{folder}',
        'GET /tags',
        'POST /tags',
        'DELETE /tags/{tag}',
        'PATCH /links/{link}',
        'DELETE /links/{link}',
        'GET /links/{link}/analytics',
    ]);
});

it('reports a removed request class', function () use ($excluded): void {
    $built = array_values(array_diff(Checks::requestOperations(), ['GET /links/{}']));

    expect(Checks::operations(Checks::spec(), $built, $excluded))->toHaveCount(1)
        ->and(Checks::operations(Checks::spec(), $built, $excluded)[0])->toContain('GET /links/{}');
});

it('reports a stale exclusion', function () use ($excluded): void {
    $problems = Checks::operations(Checks::spec(), Checks::requestOperations(), [...$excluded, 'GET /links/{link}']);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('Stale exclusion', 'GET /links/{}');
});

it('reports an exclusion that is not in the spec', function () use ($excluded): void {
    $problems = Checks::operations(Checks::spec(), Checks::requestOperations(), [...$excluded, 'GET /ghosts']);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('GET /ghosts');
});

it('reports a spec operation that is neither built nor excluded', function () use ($excluded): void {
    $spec = Checks::spec();
    $spec['paths']['/domains']['get'] = [];

    $problems = Checks::operations($spec, Checks::requestOperations(), $excluded);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('GET /domains');
});
