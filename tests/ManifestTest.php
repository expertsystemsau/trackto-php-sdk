<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @return array<string, mixed>
 */
function manifest(string $path): array
{
    /** @var array<string, mixed> */
    return json_decode((string) file_get_contents(dirname(__DIR__).'/'.$path), true, 512, JSON_THROW_ON_ERROR);
}

const MANIFESTS = ['composer.json', 'packages/trackto-client/composer.json', 'packages/trackto-laravel/composer.json'];

it('pins package names and namespaces', function (): void {
    $client = manifest('packages/trackto-client/composer.json');
    $laravel = manifest('packages/trackto-laravel/composer.json');

    expect(manifest('composer.json')['name'])->toBe('expertsystemsau/trackto-php-sdk')
        ->and($client['name'])->toBe('expertsystemsau/trackto-php-client')
        ->and($client['autoload']['psr-4'])->toBe(['ExpertSystems\\TrackTo\\' => 'src/'])
        ->and($laravel['name'])->toBe('expertsystemsau/trackto-laravel-client')
        ->and($laravel['autoload']['psr-4'])->toBe(['ExpertSystems\\TrackTo\\Laravel\\' => 'src/'])
        ->and($laravel['extra']['laravel']['providers'])->toContain('ExpertSystems\\TrackTo\\Laravel\\TrackToServiceProvider');
});

it('licenses every manifest and package under MIT', function (string $path): void {
    expect((array) manifest($path)['license'])->toBe(['MIT'])
        ->and(file_get_contents(dirname(__DIR__).'/'.dirname($path).'/LICENSE.md'))->toContain('MIT License');
})->with(MANIFESTS);

it('has no version or repositories key', function (string $path): void {
    expect(manifest($path))->not->toHaveKeys(['version'])->not->toHaveKeys(['repositories']);
})->with(MANIFESTS);

it('keeps the client framework-free', function (): void {
    $require = manifest('packages/trackto-client/composer.json')['require'];

    expect($require['php'])->toBe('^8.4');

    foreach (array_keys($require) as $name) {
        expect($name)->not->toStartWith('laravel/')->not->toStartWith('illuminate/')->not->toStartWith('orchestra/');
    }
});

it('pins the Laravel package illuminate constraint', function (): void {
    $require = manifest('packages/trackto-laravel/composer.json')['require'];

    expect($require['illuminate/support'])->toBe('^12.0||^13.0')
        ->and($require['illuminate/console'])->toBe('^12.0||^13.0')
        ->and($require['expertsystemsau/trackto-php-client'])->toBe('^0.1')
        ->and(manifest('composer.json')['require']['illuminate/console'])->toBe('^12.0||^13.0');
});

it('discovers the same provider and only the TrackTo alias from both manifests', function (): void {
    $root = manifest('composer.json')['extra']['laravel'];
    $package = manifest('packages/trackto-laravel/composer.json')['extra']['laravel'];

    expect($root)->toBe($package)
        ->and($package['providers'])->toBe(['ExpertSystems\\TrackTo\\Laravel\\TrackToServiceProvider'])
        ->and($package['aliases'])->toBe(['TrackTo' => 'ExpertSystems\\TrackTo\\Laravel\\Facades\\TrackTo']);
});

it('passes composer validate --strict', function (string $path): void {
    $process = new Process([getenv('COMPOSER_BINARY') ?: 'composer', 'validate', '--strict', '--no-check-lock', dirname(__DIR__).'/'.$path]);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
})->with(MANIFESTS);

it('states the release policy in CONTRIBUTING.md', function (): void {
    $contributing = (string) file_get_contents(dirname(__DIR__).'/CONTRIBUTING.md');

    expect($contributing)
        ->toContain('Both packages are released together with lockstep v* tags: one tag on this repository is split to both package repositories.')
        ->toContain('Versions stay 0.x until the first live consumer integrates the SDK.');
});
