<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

const ARCHIVE_ATTRIBUTES = "* text=auto eol=lf\n/tests export-ignore\n/phpunit.xml.dist export-ignore\n/.gitattributes export-ignore\n";

/**
 * @param  array<string, string>  $files  paths relative to packages/demo
 */
function archiveFixture(array $files, string $gitattributes, bool $commit = true): string
{
    $dir = sys_get_temp_dir().'/trackto-archive-'.bin2hex(random_bytes(6));
    $files += ['.gitattributes' => $gitattributes];

    foreach ($files as $path => $content) {
        $full = $dir.'/packages/demo/'.$path;
        is_dir(dirname($full)) || mkdir(dirname($full), 0777, true);
        file_put_contents($full, $content);
    }

    $GLOBALS['archiveFixtureDirs'][] = $dir;
    (new Process(['git', 'init', '-q', '-b', 'main'], $dir))->mustRun();

    if ($commit) {
        (new Process(['git', 'add', '-A'], $dir))->mustRun();
        (new Process([
            'git', '-c', 'user.email=guard@example.test', '-c', 'user.name=guard',
            '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
            'commit', '-qm', 'fixture',
        ], $dir))->mustRun();
    }

    return $dir;
}

/**
 * @return array<string, string>
 */
function archiveBaseFiles(): array
{
    return [
        'composer.json' => '{}',
        'src/Demo.php' => '<?php',
        'tests/DemoTest.php' => '<?php',
        'phpunit.xml.dist' => '<phpunit/>',
    ];
}

/**
 * @return array{int, string}
 */
function runArchiveGuard(?string $root): array
{
    $command = [PHP_BINARY, dirname(__DIR__, 2).'/bin/check-package-archive'];

    if ($root !== null) {
        $command[] = '--root='.$root;
    }

    $process = new Process($command);
    $process->run();

    return [$process->getExitCode() ?? 1, $process->getErrorOutput().$process->getOutput()];
}

afterEach(function (): void {
    foreach ($GLOBALS['archiveFixtureDirs'] ?? [] as $dir) {
        if (is_dir($dir)) {
            (new Process(['rm', '-rf', $dir]))->run();
        }
    }

    $GLOBALS['archiveFixtureDirs'] = [];
});

it('passes a fixture whose export-ignore rules cover tests and tooling', function (): void {
    expect(runArchiveGuard(archiveFixture(archiveBaseFiles(), ARCHIVE_ATTRIBUTES))[0])->toBe(0);
});

it('passes on the real repo', function (): void {
    [$code, $output] = runArchiveGuard(null);

    expect($code)->toBe(0)->and($output)->toContain('only distributable files');
});

it('fails when the tests export-ignore line is removed', function (): void {
    $attributes = str_replace("/tests export-ignore\n", '', ARCHIVE_ATTRIBUTES);

    [$code, $output] = runArchiveGuard(archiveFixture(archiveBaseFiles(), $attributes));

    expect($code)->toBe(1)->and($output)->toContain('tests');
});

it('fails when the gitattributes export-ignore line is removed', function (): void {
    $attributes = str_replace("/.gitattributes export-ignore\n", '', ARCHIVE_ATTRIBUTES);

    [$code, $output] = runArchiveGuard(archiveFixture(archiveBaseFiles(), $attributes));

    expect($code)->toBe(1)->and($output)->toContain('.gitattributes');
});

it('fails on a committed workbench directory', function (): void {
    $files = archiveBaseFiles() + ['workbench/app.php' => '<?php'];

    [$code, $output] = runArchiveGuard(archiveFixture($files, ARCHIVE_ATTRIBUTES));

    expect($code)->toBe(1)->and($output)->toContain('workbench');
});

it('fails closed when the repo has no commit', function (): void {
    expect(runArchiveGuard(archiveFixture(archiveBaseFiles(), ARCHIVE_ATTRIBUTES, commit: false))[0])->toBe(1);
});
