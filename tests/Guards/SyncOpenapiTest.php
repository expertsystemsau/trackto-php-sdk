<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/** @var list<string> $syncDirs */
$syncDirs = [];

function syncDir(): string
{
    $dir = sys_get_temp_dir().'/trackto-sync-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    $GLOBALS['syncDirs'][] = $dir;

    return $dir;
}

/**
 * @param  list<string>  $args
 * @return array{int, string}
 */
function runSync(array $args): array
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/bin/sync-openapi', ...$args]);
    $process->run();

    return [$process->getExitCode() ?? 1, $process->getErrorOutput().$process->getOutput()];
}

afterEach(function (): void {
    foreach ($GLOBALS['syncDirs'] ?? [] as $dir) {
        if (is_dir($dir)) {
            (new Process(['rm', '-rf', $dir]))->run();
        }
    }

    $GLOBALS['syncDirs'] = [];
});

it('copies the source byte for byte and overwrites a stale copy', function (): void {
    $dir = syncDir();
    $source = $dir.'/openapi.json';
    $bytes = "{\n  \"openapi\": \"3.1.0\",\n  \"info\": {\"version\": \"2026-07-06\"}\n}\n";
    file_put_contents($source, $bytes);
    mkdir($dir.'/root/spec', 0777, true);
    file_put_contents($dir.'/root/spec/openapi.json', 'stale');

    [$code, $output] = runSync(['--source='.$source, '--root='.$dir.'/root']);

    expect($code)->toBe(0)
        ->and($output)->toContain('2026-07-06')
        ->and(file_get_contents($dir.'/root/spec/openapi.json'))->toBe($bytes)
        ->and(glob($dir.'/root/spec/.openapi-*'))->toBe([]);
});

it('exits 1 naming a missing source and writes nothing', function (): void {
    $dir = syncDir();
    $missing = $dir.'/nope/openapi.json';

    [$code, $output] = runSync(['--source='.$missing, '--root='.$dir.'/root']);

    expect($code)->toBe(1)
        ->and($output)->toContain('source not found: '.$missing)
        ->and(file_exists($dir.'/root/spec/openapi.json'))->toBeFalse();
});

it('leaves the target unchanged for invalid JSON', function (): void {
    $dir = syncDir();
    file_put_contents($dir.'/in.json', '{not json');
    mkdir($dir.'/root/spec', 0777, true);
    file_put_contents($dir.'/root/spec/openapi.json', 'keep');

    [$code, $output] = runSync(['--source='.$dir.'/in.json', '--root='.$dir.'/root']);

    expect($code)->toBe(1)
        ->and($output)->toContain('not valid JSON')
        ->and(file_get_contents($dir.'/root/spec/openapi.json'))->toBe('keep');
});

it('leaves the target unchanged when info.version is missing', function (): void {
    $dir = syncDir();
    file_put_contents($dir.'/in.json', '{"openapi":"3.1.0","info":{}}');
    mkdir($dir.'/root/spec', 0777, true);
    file_put_contents($dir.'/root/spec/openapi.json', 'keep');

    [$code, $output] = runSync(['--source='.$dir.'/in.json', '--root='.$dir.'/root']);

    expect($code)->toBe(1)
        ->and($output)->toContain('info.version')
        ->and(file_get_contents($dir.'/root/spec/openapi.json'))->toBe('keep');
});

it('prints usage for an unknown argument', function (): void {
    [$code, $output] = runSync(['--bogus']);

    expect($code)->toBe(1)->and($output)->toContain('Usage: sync-openapi');
});
