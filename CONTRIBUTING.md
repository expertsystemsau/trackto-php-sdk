# Contributing

## Development

The repository holds two packages under `packages/`: `trackto-client` and `trackto-laravel`. The root `composer.json` is for development only and is never published. Its autoload maps both packages' `src` directories, so there are no path repositories and no `version` fields in any manifest.

`composer test` is the gate. Its seven steps are PHPStan, Pint (check mode), Pest type coverage, the Pest unit suites, `bin/check-package-deps`, `bin/check-package-archive` and the API key scan.

Every runtime `require` in a package manifest must match the root `require` byte-for-byte. `bin/check-package-deps` fails when an entry is missing from the root or carries a different constraint.

## Release policy

Both packages are released together with lockstep v* tags: one tag on this repository is split to both package repositories.

Versions stay 0.x until the first live consumer integrates the SDK.

Tags take the form `v<major>.<minor>.<patch>[-<prerelease>]`, enforced by `bin/check-tag`. Each real tag gets a manually published GitHub release on this repository, which updates `CHANGELOG.md`. The split repositories' releases link to it, so the order of tag push and release does not matter. The split repositories ship no changelog of their own.

## Split repositories

`split.yml` publishes `packages/trackto-client` to `expertsystemsau/trackto-php-client` and `packages/trackto-laravel` to `expertsystemsau/trackto-laravel-client` on every push to `main` and every valid `v*` tag. The split repositories are read-only.

Every commit that touches `packages/**` is replayed into their public history permanently. Write commit subjects for outside readers and never force-push `main`.

## Credentials

`SPLIT_PAT` is a fine-grained token owned by `expertsystemsau`, limited to the two split repositories with Contents read and write. The secret was created on 2026-10-11. The organisation caps fine-grained tokens at 366 days, so rotate it before 2027-10-11 at the latest.

`actions/checkout` stays at `v4` in `split.yml`: the split action breaks on newer majors, and Dependabot ignores those bumps.

## Known interaction

`main` is protected with required status checks, which blocks `update-changelog.yml`'s direct push of `CHANGELOG.md`. Resolve this before the first real release, either with a ruleset bypass for the Actions bot or by switching the workflow to open a pull request.
