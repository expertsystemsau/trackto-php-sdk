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

it('never triggers on pull_request_target or workflow_run', function (): void {
    foreach (workflowFiles() as $file) {
        // YAML 1.1 would parse a bare `on:` key as boolean true; Symfony keeps the string.
        $on = workflow(basename($file))['on'];

        // `on` is a map, a list of event names, or a single event name.
        $triggers = is_array($on) ? array_merge(array_keys($on), array_filter($on, 'is_string')) : [$on];

        expect($triggers)->not->toContain('pull_request_target')->not->toContain('workflow_run');
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

it('triggers split.yml only on push to main and v* tags', function (): void {
    $on = (array) workflow('split.yml')['on'];

    expect(array_keys($on))->toBe(['push'])
        ->and($on['push']['branches'])->toBe(['main'])
        ->and($on['push']['tags'])->toBe(['v*']);
});

it('runs split.yml read-only', function (): void {
    expect(workflow('split.yml')['permissions'])->toBe(['contents' => 'read']);
});

it('maps each package to its read-only repo in split.yml', function (): void {
    /** @var list<array{name: string, path: string, target: string}> $packages */
    $packages = workflow('split.yml')['jobs']['split']['strategy']['matrix']['package'];

    expect($packages)->toBe([
        ['name' => 'trackto-client', 'path' => 'packages/trackto-client', 'target' => 'expertsystemsau/trackto-php-client'],
        ['name' => 'trackto-laravel', 'path' => 'packages/trackto-laravel', 'target' => 'expertsystemsau/trackto-laravel-client'],
    ]);
});

it('serialises split runs per package and ref without cancelling', function (): void {
    /** @var array{group: string, 'cancel-in-progress': bool} $concurrency */
    $concurrency = workflow('split.yml')['jobs']['split']['concurrency'];

    expect($concurrency['group'])->toContain('${{ matrix.package.name }}')
        ->and($concurrency['group'])->toContain('${{ github.ref }}')
        ->and($concurrency['cancel-in-progress'])->toBeFalse();
});

it('keeps actions/checkout on a v4 SHA with default credentials in split.yml', function (): void {
    /** @var list<array<string, mixed>> $steps */
    $steps = workflow('split.yml')['jobs']['split']['steps'];
    $text = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/split.yml');

    expect($steps[0]['uses'])->toBe('actions/checkout@11d5960a326750d5838078e36cf38b85af677262')
        ->and($text)->toContain('actions/checkout@11d5960a326750d5838078e36cf38b85af677262 # v4.')
        ->and($steps[0]['with']['fetch-depth'])->toBe(0)
        ->and($steps[0]['with'])->not->toHaveKey('persist-credentials')
        ->and($text)->toContain('# Pinned to v4 on purpose (do NOT bump to match the other workflows).');
});

it('validates the tag before splitting in split.yml', function (): void {
    /** @var list<array<string, mixed>> $steps */
    $steps = workflow('split.yml')['jobs']['split']['steps'];
    $names = array_column($steps, 'name');
    $guard = $steps[array_search('Reject malformed tags', $names, true)];
    $splitIndex = array_search('Split ${{ matrix.package.name }}', $names, true);

    expect(array_search('Reject malformed tags', $names, true))->toBeLessThan($splitIndex)
        ->and($guard['if'])->toBe("github.ref_type == 'tag'")
        ->and($guard['env'])->toBe(['TAG' => '${{ github.ref_name }}'])
        ->and($guard['run'])->toContain('bin/check-tag "$TAG"');
});

it('keeps every expression out of split.yml run blocks', function (): void {
    /** @var list<array<string, mixed>> $steps */
    $steps = workflow('split.yml')['jobs']['split']['steps'];

    foreach ($steps as $step) {
        if (isset($step['run'])) {
            expect($step['run'])->not->toContain('${{');
        }
    }
});

it('creates the split release only when missing and without generated notes', function (): void {
    /** @var list<array<string, mixed>> $steps */
    $steps = workflow('split.yml')['jobs']['split']['steps'];
    $release = (string) $steps[array_key_last($steps)]['run'];

    expect(strpos($release, 'gh release view'))->toBeInt()
        ->and(strpos($release, 'gh release view'))->toBeLessThan(strpos($release, 'gh release create'))
        ->and($release)->toContain('--verify-tag')
        ->and($release)->not->toContain('--generate-notes');
});

it('marks pre-release tags and fails on view errors other than not found', function (): void {
    /** @var list<array<string, mixed>> $steps */
    $steps = workflow('split.yml')['jobs']['split']['steps'];
    $release = (string) $steps[array_key_last($steps)]['run'];

    expect($release)->toContain('release not found')->and($release)->toContain('--prerelease')->and($release)->toContain('$pre');
});

it('pins every third-party action to a full commit SHA', function (): void {
    foreach (workflowFiles() as $file) {
        preg_match_all('/^\s*(?:-\s+)?uses:\s*(\S+)/m', (string) file_get_contents($file), $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach ($matches[1] as $uses) {
            expect($uses)->toMatch('/@[0-9a-f]{40}$/');
        }
    }
});
