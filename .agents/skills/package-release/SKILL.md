---
name: package-release
description: "Use this skill when preparing Laravel package releases: CHANGELOG.md updates, generated release notes, GitHub release workflows, version checks, tags, release validation, or release automation changes. Never publish autonomously."
license: MIT
metadata:
  author: laravel
---

# Package Release

## Primary Goal

Prepare a safe package release checklist and implementation without tagging, pushing, or publishing unless the user explicitly approves that action.

## Workflow

1. Review `CHANGELOG.md`, generated release notes config, GitHub release workflows, open diff, and pending package changes.
2. Validate the release state with `composer test` before recommending a release.
3. Confirm whether version metadata needs to change; many Laravel packages rely on Git tags rather than a hardcoded package version.
4. Review tag naming (lockstep `v*` tags, one tag releases both packages; semantic versioning from 1.0.0, so a breaking change needs a new major), release branch, split workflow, and GitHub release behavior before any release command.
5. Do not tag, push, or publish without explicit user approval.

## References

- `CHANGELOG.md` (root only; the split repositories ship none)
- `.github/workflows/split.yml`
- `.github/workflows/tests.yml`
- `composer.json`, `CONTRIBUTING.md` (release policy) and `bin/check-tag`

## Examples

- Prepare a release by checking changelog coverage, running `composer test`, and drafting the tag command for user approval.
- Check that `bin/check-tag` accepts the intended tag before drafting the tag command.

## Anti-Patterns

- Creating tags, pushing branches, or publishing releases without explicit approval.
- Skipping `composer test` before a release recommendation.
- Treating generated release notes as a replacement for meaningful `CHANGELOG.md` entries.
- Changing release workflows without checking the supported matrix in `.github/workflows/tests.yml`.
