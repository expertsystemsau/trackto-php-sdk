<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * @return list<string>
 */
function workflowFiles(): array
{
    return glob(dirname(__DIR__, 2).'/.github/workflows/*.yml') ?: [];
}

/**
 * @return array<string, mixed>
 */
function workflow(string $name): array
{
    /** @var array<string, mixed> */
    return Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/'.$name);
}

it('ignores actions/checkout major updates in dependabot', function (): void {
    /** @var array{updates: list<array<string, mixed>>} $config */
    $config = Yaml::parseFile(dirname(__DIR__, 2).'/.github/dependabot.yml');

    $actions = array_values(array_filter(
        $config['updates'],
        fn (array $update): bool => $update['package-ecosystem'] === 'github-actions',
    ));

    expect($actions)->toHaveCount(1)
        ->and($actions[0]['ignore'])->toContain([
            'dependency-name' => 'actions/checkout',
            'update-types' => ['version-update:semver-major'],
        ]);
});

it('has no auto-merge workflow', function (): void {
    expect(workflowFiles())->not->toBeEmpty();

    foreach (workflowFiles() as $file) {
        $text = (string) file_get_contents($file);

        expect(basename($file))->not->toContain('auto-merge')
            ->and($text)->not->toContain('dependabot/fetch-metadata')
            ->and($text)->not->toContain('gh pr merge');
    }
});

it('never triggers on pull_request_target', function (): void {
    foreach (workflowFiles() as $file) {
        // YAML 1.1 would parse a bare `on:` key as boolean true; Symfony keeps the string.
        $on = workflow(basename($file))['on'];

        expect(array_keys((array) $on))->not->toContain('pull_request_target');
    }
});

it('runs tests.yml read-only with no secrets', function (): void {
    $text = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

    expect(workflow('tests.yml')['permissions'])->toBe(['contents' => 'read'])
        ->and($text)->not->toContain('secrets.');
});

it('runs the 8-lane matrix in tests.yml', function (): void {
    /** @var array{php: list<float>, laravel: list<string>, stability: list<string>, include: list<array<string, string>>} $matrix */
    $matrix = workflow('tests.yml')['jobs']['tests']['strategy']['matrix'];

    expect($matrix['php'])->toBe([8.4, 8.5])
        ->and($matrix['laravel'])->toBe(['12.*', '13.*'])
        ->and($matrix['stability'])->toBe(['prefer-lowest', 'prefer-stable'])
        ->and($matrix['include'])->toHaveCount(2)
        ->and(count($matrix['php']) * count($matrix['laravel']) * count($matrix['stability']))->toBe(8);
});

it('proves the client standalone in tests.yml', function (): void {
    /** @var array{strategy: array{matrix: array<string, list<mixed>>}, steps: list<array<string, mixed>>} $job */
    $job = workflow('tests.yml')['jobs']['client-standalone'];
    $names = array_column($job['steps'], 'name');

    expect(count($job['strategy']['matrix']['php']) * count($job['strategy']['matrix']['stability']))->toBe(4)
        ->and($names)->toContain('Fail if a framework crept into the client package')
        ->and($names)->toContain('Run the repo guards');
});

it('updates the changelog only on a released release', function (): void {
    expect(workflow('update-changelog.yml')['on']['release']['types'])->toBe(['released']);
});
