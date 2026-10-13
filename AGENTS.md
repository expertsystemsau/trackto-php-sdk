# track.to PHP SDK

This repository is a two-package monorepo for the track.to PHP SDK. Keep the packages focused, idiomatic, and easy to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with each package's own name (see Monorepo layout).
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Monorepo layout

| Path | Composer package | Namespace |
| --- | --- | --- |
| `packages/trackto-client` | `expertsystemsau/trackto-php-client` | `ExpertSystems\TrackTo` |
| `packages/trackto-laravel` | `expertsystemsau/trackto-laravel-client` | `ExpertSystems\TrackTo\Laravel` |

The root `composer.json` is dev-only and never published. Its psr-4 autoload covers both `src` directories.

- The gate is `cd ~/projects/trackto-php-sdk && composer test`. Never run the parent project's `composer test` for SDK work. It runs seven steps: PHPStan, Pint (check mode), Pest type coverage, Pest unit suites, `bin/check-package-deps`, `bin/check-package-archive`, and the API key scan.
- The client package stays framework-free: no `laravel/*`, `illuminate/*` or `orchestra/*` in its `require`. The `client-standalone` CI job and `bin/check-package-deps` enforce it.
- No `version` field, `repositories` entry, or path repository in any manifest.
- Keep the root `require` in sync with every package runtime `require`, constraint strings identical. Sibling package requires are exempt.
- `actions/checkout` stays pinned at `v4` in `.github/workflows/split.yml`. Do not bump it.
- Every commit that touches `packages/**` becomes permanent public history in the split repositories. Write commit subjects for outside readers and never force-push `main`.
- Release policy, `SPLIT_PAT` rotation, and the hand-written changelog step live in `CONTRIBUTING.md`. Do not tag, push, or publish without explicit approval.

## Operational notes

- Set `PAO_DISABLE=1` when reading tool output while diagnosing: `laravel/pao` compacts output under agents. Exit codes are unchanged.
- Do not add an arch expectation that forbids the Illuminate namespace for the client. It fatals on prefer-lowest. The `client-standalone` job covers the rule.
- The root `conflict` on `brianium/paratest` `7.26.1` exists because that release breaks `pest --parallel` on Pest 5.3.1. Remove it once a Pest 5 release requiring paratest `^7.26.1` ships (pestphp/pest#1959).
- `laravel/pao` is kept. It may be cut only if it breaks a CI lane, and the cut must be recorded.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
