<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @return array{int, string}
 */
function runSecretScan(string $cwd): array
{
    $process = new Process(['sh', dirname(__DIR__, 2).'/bin/scan-secrets'], $cwd);
    $process->run();

    return [$process->getExitCode() ?? 1, $process->getOutput()];
}

/**
 * Builds a throwaway repo holding one file; the key is generated at runtime so no key-shaped literal is tracked.
 */
function secretFixture(string $content, bool $track = true, bool $init = true): string
{
    $dir = sys_get_temp_dir().'/trackto-secrets-'.bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents($dir.'/config.txt', $content);
    $GLOBALS['secretFixtureDirs'][] = $dir;

    if (! $init) {
        return $dir;
    }

    (new Process(['git', 'init', '-q', '-b', 'main'], $dir))->mustRun();

    if ($track) {
        (new Process(['git', 'add', 'config.txt'], $dir))->mustRun();
    }

    return $dir;
}

function generatedKey(int $length = 40): string
{
    return 'tk_'.substr(bin2hex(random_bytes(20)), 0, $length);
}

afterEach(function (): void {
    foreach ($GLOBALS['secretFixtureDirs'] ?? [] as $dir) {
        if (is_dir($dir)) {
            (new Process(['rm', '-rf', $dir]))->run();
        }
    }

    $GLOBALS['secretFixtureDirs'] = [];
});

it('fails on a tracked key and prints the file name but not the key', function (): void {
    $key = generatedKey();

    [$code, $output] = runSecretScan(secretFixture("token={$key}\n"));

    expect($code)->not->toBe(0)->and($output)->toContain('config.txt')->and($output)->not->toContain($key);
});

it('passes on a key one character short', function (): void {
    expect(runSecretScan(secretFixture('token='.generatedKey(39)."\n"))[0])->toBe(0);
});

it('ignores untracked files', function (): void {
    expect(runSecretScan(secretFixture('token='.generatedKey()."\n", track: false))[0])->toBe(0);
});

it('passes at the real repo root', function (): void {
    expect(runSecretScan(dirname(__DIR__, 2))[0])->toBe(0);
});

it('fails when git cannot scan, instead of reporting clean', function (): void {
    expect(runSecretScan(secretFixture('token=none', init: false))[0])->not->toBe(0);
});

it('is the script composer runs for scan:secrets', function (): void {
    $scripts = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR)['scripts'];

    expect($scripts['scan:secrets'])->toBe(['sh bin/scan-secrets']);
});
