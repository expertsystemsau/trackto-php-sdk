<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;

/**
 * @return array<string, string> method name => short return type, from the `@method static` lines of a docblock
 */
function facadeParityMethods(string $docblock): array
{
    preg_match_all('/@method\s+static\s+(\S+)\s+(\w+)\(/', $docblock, $matches, PREG_SET_ORDER);

    $methods = [];

    foreach ($matches as $match) {
        $methods[$match[2]] = $match[1];
    }

    ksort($methods);

    return $methods;
}

function facadeParityDocblock(): string
{
    return (string) (new ReflectionClass(TrackTo::class))->getDocComment();
}

/**
 * @return array<string, string> the public links, folders, tags and with* methods TrackToClient declares itself
 */
function facadeParityClientMethods(): array
{
    $methods = [];

    foreach ((new ReflectionClass(TrackToClient::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $name = $method->getName();

        if ($method->getDeclaringClass()->getName() !== TrackToClient::class || ! (in_array($name, ['links', 'folders', 'tags'], true) || str_starts_with($name, 'with'))) {
            continue;
        }

        $type = $method->getReturnType();
        $short = $type instanceof ReflectionNamedType ? $type->getName() : throw new LogicException("{$name}() needs a named return type.");
        $methods[$name] = $short === 'static' ? 'TrackToClient' : (new ReflectionClass($short))->getShortName();
    }

    ksort($methods);

    return $methods;
}

it('declares exactly the client methods as @method lines', function (): void {
    expect(facadeParityMethods(facadeParityDocblock()))->toHaveCount(12)
        ->and(facadeParityMethods(facadeParityDocblock()))->toBe(facadeParityClientMethods());
});

it('compares sets, so line order does not matter', function (): void {
    $lines = array_filter(explode("\n", facadeParityDocblock()), static fn (string $line): bool => str_contains($line, '@method'));

    expect(facadeParityMethods(implode("\n", array_reverse($lines))))->toBe(facadeParityClientMethods());
});

it('reports a missing or an extra @method line', function (): void {
    $lines = array_values(array_filter(explode("\n", facadeParityDocblock()), static fn (string $line): bool => str_contains($line, '@method')));

    expect(facadeParityMethods(implode("\n", array_slice($lines, 1))))->not->toBe(facadeParityClientMethods())
        ->and(facadeParityMethods(implode("\n", [...$lines, ' * @method static TrackToClient withNothing()'])))->not->toBe(facadeParityClientMethods());
});

it('keeps fake() a real static method outside the @method block', function (): void {
    $fake = new ReflectionMethod(TrackTo::class, 'fake');
    $parameters = $fake->getParameters();

    expect($fake->isPublic() && $fake->isStatic())->toBeTrue()
        ->and($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('responses')
        ->and((string) $parameters[0]->getType())->toBe('array')
        ->and($parameters[0]->getDefaultValue())->toBe([])
        ->and((string) $fake->getReturnType())->toBe(MockClient::class)
        ->and(facadeParityMethods(facadeParityDocblock()))->not->toHaveKey('fake');
});

it('throws the framework error, not a track.to one, when the facade has no application', function (): void {
    $application = TrackTo::getFacadeApplication();

    try {
        TrackTo::clearResolvedInstances();
        TrackTo::setFacadeApplication(null);

        expect(static fn () => TrackTo::links())->toThrow(RuntimeException::class, 'A facade root has not been set.');
    } finally {
        TrackTo::setFacadeApplication($application);
    }
});
