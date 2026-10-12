<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @param  list<string>  $args
 */
function tagGuardExit(array $args): int
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/bin/check-tag', ...$args]);
    $process->run();

    return $process->getExitCode() ?? 1;
}

it('accepts release tag :tag', function (string $tag): void {
    expect(tagGuardExit([$tag]))->toBe(0);
})->with(['v0.1.0', 'v0.1.0-rc.1', 'v0.0.0-split.1', 'v1.2.3', 'v10.20.30-alpha.beta-1']);

it('rejects tag :tag', function (string $tag): void {
    expect(tagGuardExit([$tag]))->toBe(1);
})->with([
    '0.1.0',
    'v1',
    'release-1',
    'v1.2',
    'v01.2.3',
    'v1.2.3+b',
    'v1.2.3-',
    'v1.2.3-rc..1',
    'V0.1.0',
    'empty' => '',
    'v0.1.0;id',
    'trailing newline' => "v0.1.0\n",
]);

it('rejects a call with no tag', function (): void {
    expect(tagGuardExit([]))->toBe(1);
});

it('rejects a call with two tags', function (): void {
    expect(tagGuardExit(['v0.1.0', 'v0.1.1']))->toBe(1);
});
