<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/** @var list<string> $depsFixtureDirs */
$depsFixtureDirs = [];

/**
 * @param  array<string, mixed>  $root
 * @param  array<string, array<string, mixed>>  $packages  keyed by package directory name
 */
function depsFixture(array $root, array $packages): string
{
    $dir = sys_get_temp_dir().'/trackto-deps-'.bin2hex(random_bytes(6));
    mkdir($dir.'/packages', 0777, true);
    file_put_contents($dir.'/composer.json', json_encode($root, JSON_THROW_ON_ERROR));

    foreach ($packages as $name => $manifest) {
        mkdir($dir.'/packages/'.$name, 0777, true);
        file_put_contents($dir.'/packages/'.$name.'/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    $GLOBALS['depsFixtureDirs'][] = $dir;

    return $dir;
}

/**
 * @return array{int, string}
 */
function runDepsGuard(?string $root): array
{
    $command = [PHP_BINARY, dirname(__DIR__, 2).'/bin/check-package-deps'];

    if ($root !== null) {
        $command[] = '--root='.$root;
    }

    $process = new Process($command);
    $process->run();

    return [$process->getExitCode() ?? 1, $process->getErrorOutput().$process->getOutput()];
}

/**
 * @return array{array<string, mixed>, array<string, array<string, mixed>>}
 */
function depsBase(): array
{
    return [
        ['require' => ['php' => '^8.4', 'illuminate/support' => '^12.0||^13.0'], 'require-dev' => ['pestphp/pest' => '^5.0']],
        [
            'client' => ['name' => 'expertsystemsau/trackto-php-client', 'require' => ['php' => '^8.4']],
            'laravel' => ['name' => 'expertsystemsau/trackto-laravel-client', 'require' => ['php' => '^8.4', 'illuminate/support' => '^12.0||^13.0']],
        ],
    ];
}

afterEach(function (): void {
    foreach ($GLOBALS['depsFixtureDirs'] ?? [] as $dir) {
        if (is_dir($dir)) {
            (new Process(['rm', '-rf', $dir]))->run();
        }
    }

    $GLOBALS['depsFixtureDirs'] = [];
});

it('passes a clean fixture', function (): void {
    [$root, $packages] = depsBase();

    expect(runDepsGuard(depsFixture($root, $packages))[0])->toBe(0);
});

it('lets a package set its own PHP floor', function (): void {
    [$root, $packages] = depsBase();
    $packages['client']['require']['php'] = '^8.2';

    expect(runDepsGuard(depsFixture($root, $packages))[0])->toBe(0);
});

it('passes on the real repo', function (): void {
    [$code, $output] = runDepsGuard(null);

    expect($code)->toBe(0)->and($output)->toContain('Package manifests are in sync');
});

it('fails when a package constraint differs from the root', function (): void {
    [$root, $packages] = depsBase();
    $packages['laravel']['require']['illuminate/support'] = '^12.0';

    [$code, $output] = runDepsGuard(depsFixture($root, $packages));

    expect($code)->toBe(1)->and($output)->toContain('illuminate/support', '^12.0||^13.0');
});

it('fails when a package requires something the root lacks', function (): void {
    [$root, $packages] = depsBase();
    $packages['laravel']['require']['vendor/extra'] = '^1.0';

    [$code, $output] = runDepsGuard(depsFixture($root, $packages));

    expect($code)->toBe(1)->and($output)->toContain('vendor/extra');
});

it('fails on a forbidden dependency in a package require', function (): void {
    [$root, $packages] = depsBase();
    $packages['laravel']['require']['spatie/laravel-package-tools'] = '^1.0';
    $root['require']['spatie/laravel-package-tools'] = '^1.0';

    [$code, $output] = runDepsGuard(depsFixture($root, $packages));

    expect($code)->toBe(1)->and($output)->toContain('spatie/laravel-package-tools');
});

it('fails on a forbidden dependency in the root require-dev', function (): void {
    [$root, $packages] = depsBase();
    $root['require-dev']['saloonphp/laravel-plugin'] = '^3.0';

    [$code, $output] = runDepsGuard(depsFixture($root, $packages));

    expect($code)->toBe(1)->and($output)->toContain('saloonphp/laravel-plugin');
});

it('fails when the client package requires a framework package', function (): void {
    [$root, $packages] = depsBase();
    $packages['client']['require']['illuminate/support'] = '^12.0||^13.0';

    [$code, $output] = runDepsGuard(depsFixture($root, $packages));

    expect($code)->toBe(1)->and($output)->toContain('expertsystemsau/trackto-php-client');
});

it('allows a require on a sibling workspace package absent from the root', function (): void {
    [$root, $packages] = depsBase();
    $packages['laravel']['require']['expertsystemsau/trackto-php-client'] = '^0.1';

    expect(runDepsGuard(depsFixture($root, $packages))[0])->toBe(0);
});

it('fails on a manifest that is not valid JSON', function (): void {
    [$root, $packages] = depsBase();
    $dir = depsFixture($root, $packages);
    file_put_contents($dir.'/packages/laravel/composer.json', '{not json');

    [$code, $output] = runDepsGuard($dir);

    expect($code)->toBe(1)->and($output)->toContain('packages/laravel/composer.json');
});

it('fails when there are no package manifests', function (): void {
    [$root] = depsBase();

    expect(runDepsGuard(depsFixture($root, []))[0])->toBe(1);
});
