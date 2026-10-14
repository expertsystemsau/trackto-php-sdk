<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Tests\Contract\Fixtures;

use ExpertSystems\TrackTo\Support\Omitted;
use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;
use ReflectionFunction;

/** A class whose name merely contains "int": the substring match this test replaced called it an int. */
final class Point {}

function typedParameters(int $plain, ?int $nullable, int|Omitted|null $union, string $text, Point|Omitted $point, $untyped): void {}

it('detects int through reflection members, not the type string', function (string $parameter, bool $expected): void {
    $type = null;

    foreach ((new ReflectionFunction(__NAMESPACE__.'\\typedParameters'))->getParameters() as $candidate) {
        if ($candidate->getName() === $parameter) {
            $type = $candidate->getType();
        }
    }

    expect(Checks::isIntType($type))->toBe($expected);
})->with([
    'int' => ['plain', true],
    '?int' => ['nullable', true],
    'int|Omitted|null' => ['union', true],
    'string' => ['text', false],
    'Point|Omitted' => ['point', false],
    'no type' => ['untyped', false],
]);
