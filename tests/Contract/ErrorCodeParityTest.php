<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ErrorMapper;
use ExpertSystems\TrackTo\Exceptions\NotFoundException;
use ExpertSystems\TrackTo\Exceptions\ServerException;
use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;

it('maps every spec error code to an exception and nothing else', function (): void {
    expect(Checks::specErrorCodes(Checks::spec()))->toHaveCount(10)
        ->and(Checks::errorCodes(Checks::spec(), ErrorMapper::BY_NAME))->toBe([]);
});

it('reports a code added to the spec', function (): void {
    $spec = Checks::spec();
    $spec['components']['schemas']['ApiError']['properties']['name']['description'] = str_replace(
        'bad_request.',
        'bad_request, payment_required.',
        $spec['components']['schemas']['ApiError']['properties']['name']['description'],
    );

    $problems = Checks::errorCodes($spec, ErrorMapper::BY_NAME);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('payment_required');
});

it('reports a mapped code the spec dropped', function (): void {
    $problems = Checks::errorCodes(Checks::spec(), ErrorMapper::BY_NAME + ['legacy_error' => NotFoundException::class]);

    expect($problems[0])->toContain('legacy_error');
});

it('reports a class that breaks the naming rule', function (): void {
    $byName = [...ErrorMapper::BY_NAME, 'not_found' => ErrorMapper::class];
    $problems = Checks::errorCodes(Checks::spec(), $byName);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('not_found', 'TrackToException');
});

it('reports a mapped class with the wrong name', function (): void {
    $problems = Checks::errorCodes(Checks::spec(), [...ErrorMapper::BY_NAME, 'not_found' => ServerException::class]);

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('NotFoundException');
});

it('reports a description it cannot parse', function (): void {
    $spec = Checks::spec();
    $spec['components']['schemas']['ApiError']['properties']['name']['description'] = 'An identifier.';

    expect(Checks::errorCodes($spec, ErrorMapper::BY_NAME))->toHaveCount(1);
});
