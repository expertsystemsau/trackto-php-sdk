<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;

it('has a DTO parameter for every resource schema property', function (): void {
    expect(Checks::dtoFields(Checks::spec(), Checks::dtoParameters()))->toBe([]);
});

it('has a DTO parameter for every inline response property', function (): void {
    expect(Checks::inlineFields(Checks::spec()))->toBe([]);
});

it('maps schemas to DTOs by convention plus the LinkTagResource exception', function (): void {
    expect(array_keys(Checks::dtoParameters()))->toEqualCanonicalizing(['LinkResource', 'FolderResource', 'TagResource', 'LinkTagResource'])
        ->and(Checks::dtoParameters()['LinkResource'])->toContain('destinationUrl', 'clickCount');
});

it('reports a DTO field that was removed', function (): void {
    $params = Checks::dtoParameters();
    $params['LinkResource'] = array_values(array_diff($params['LinkResource'], ['slug']));

    $problems = Checks::dtoFields(Checks::spec(), $params);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('LinkResource.slug');
});

it('reports a property added to the spec', function (): void {
    $spec = Checks::spec();
    $spec['components']['schemas']['LinkResource']['properties']['utm_source'] = ['type' => 'string'];

    $problems = Checks::dtoFields($spec, Checks::dtoParameters());

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('LinkResource.utm_source');
});

it('reports a resource schema with no DTO', function (): void {
    $spec = Checks::spec();
    $spec['components']['schemas']['DomainResource'] = ['properties' => ['id' => []]];

    expect(Checks::dtoFields($spec, Checks::dtoParameters())[0])->toContain('DomainResource');
});

/**
 * A copy of the spec with the analytics 200 schema edited by the callback.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $edit
 * @return array<string, mixed>
 */
function inlineSpecWith(callable $edit): array
{
    $spec = Checks::spec();
    $response = &$spec['paths']['/links/{link}/analytics']['get']['responses']['200']['content']['application/json'];
    $response['schema'] = $edit($response['schema']);

    return $spec;
}

it('reports a property added to the inline analytics root', function (): void {
    $spec = inlineSpecWith(function (array $schema): array {
        $schema['properties']['uniqueVisitors'] = ['type' => 'integer'];

        return $schema;
    });

    $problems = Checks::inlineFields($spec);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('uniqueVisitors', 'LinkAnalyticsData');
});

it('reports a property added to the overTime items', function (): void {
    $spec = inlineSpecWith(function (array $schema): array {
        $schema['properties']['overTime']['items']['properties']['uniques'] = ['type' => 'integer'];

        return $schema;
    });

    $problems = Checks::inlineFields($spec);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('uniques', 'AnalyticsDayData');
});

it('reports an inline object with no table entry', function (): void {
    $spec = inlineSpecWith(function (array $schema): array {
        $schema['properties']['breakdown'] = ['type' => 'object', 'properties' => ['web' => ['type' => 'integer']]];

        return $schema;
    });

    expect(implode("\n", Checks::inlineFields($spec)))->toContain('breakdown', 'no INLINE_RESPONSES entry');
});

it('reports an inline operation that is missing from the spec', function (): void {
    $spec = Checks::spec();
    unset($spec['paths']['/links/{link}/analytics']);

    $problems = Checks::inlineFields($spec);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('GET /links/{}/analytics');
});

it('reports an unmapped inline response on a new operation', function (): void {
    $spec = Checks::spec();
    $spec['paths']['/domains']['get']['responses']['200']['content']['application/json']['schema'] = [
        'type' => 'object',
        'properties' => ['count' => ['type' => 'integer']],
    ];

    $problems = Checks::inlineFields($spec);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('GET /domains', 'inline response schema');
});

it('does not report a data envelope that references a schema', function (): void {
    $spec = Checks::spec();
    $spec['paths']['/domains']['get']['responses']['200']['content']['application/json']['schema'] = [
        'type' => 'object',
        'properties' => ['data' => ['$ref' => '#/components/schemas/LinkResource']],
    ];

    expect(Checks::inlineFields($spec))->toBe([]);
});
