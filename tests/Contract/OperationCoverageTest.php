<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;

$excluded = require __DIR__.'/excluded-operations.php';

it('builds or excludes every spec operation', function () use ($excluded): void {
    expect(Checks::operations(Checks::spec(), Checks::requestOperations(), $excluded))->toBe([]);
});

it('reads the built operations from the request classes', function () use ($excluded): void {
    $all = [...Checks::requestOperations(), ...array_map(Checks::normaliseOperation(...), $excluded)];
    $spec = Checks::specOperations(Checks::spec());
    sort($all);
    sort($spec);

    expect($all)->toBe($spec)
        ->and(Checks::requestOperations())->toContain('GET /links/{}/analytics');
});

it('excludes nothing: every spec operation is built', function () use ($excluded): void {
    expect($excluded)->toBe([]);
});

it('reports the removal of any one built operation', function (string $operation) use ($excluded): void {
    $built = array_values(array_diff(Checks::requestOperations(), [$operation]));
    $problems = Checks::operations(Checks::spec(), $built, $excluded);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain($operation);
})->with(fn (): array => array_combine(Checks::requestOperations(), array_map(fn (string $op): array => [$op], Checks::requestOperations())));

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
