<?php

declare(strict_types=1);

const LARAVEL_GUARD_NAMESPACE = 'ExpertSystems\\TrackTo\\Laravel\\';

/**
 * @return array<string, string> class name => source file
 */
function laravelGuardSources(): array
{
    $root = dirname(__DIR__).'/packages/trackto-laravel/src';
    $sources = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') {
            $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
            $sources[LARAVEL_GUARD_NAMESPACE.str_replace('/', '\\', $relative)] = $file->getPathname();
        }
    }

    return $sources;
}

it('maps every source file to a class and finds the provider, facade and factory', function (): void {
    $classes = array_keys(laravelGuardSources());

    expect($classes)->toContain(
        LARAVEL_GUARD_NAMESPACE.'TrackToServiceProvider',
        LARAVEL_GUARD_NAMESPACE.'Facades\\TrackTo',
        LARAVEL_GUARD_NAMESPACE.'ClientFactory',
    );

    foreach ($classes as $class) {
        expect(class_exists($class) || interface_exists($class) || trait_exists($class))->toBeTrue($class);
    }
});

it('declares no static property in any Laravel package class', function (): void {
    $offenders = [];

    foreach (array_keys(laravelGuardSources()) as $class) {
        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            // Statics inherited from Facade and ServiceProvider are the framework's, not this package's.
            if ($property->getDeclaringClass()->getName() === $class) {
                $offenders[] = $class.'::$'.$property->getName();
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('never installs a global Saloon mock in package source', function (): void {
    $offenders = [];

    foreach (laravelGuardSources() as $class => $path) {
        $source = (string) file_get_contents($path);

        if (str_contains($source, 'MockClient::global(') || str_contains($source, 'Saloon::fake(')) {
            $offenders[] = $class;
        }
    }

    expect($offenders)->toBe([]);
});
