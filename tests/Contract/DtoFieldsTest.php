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
