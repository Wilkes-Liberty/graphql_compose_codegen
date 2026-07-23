# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses Drupal-style version tags (`1.0.0`, `1.0.1`, etc.).

---

## [Unreleased]

## [1.0.0] — 2026-07-23

### Added

- GitHub Actions `tests.yml`: a PHPUnit matrix that runs the Unit and Kernel
  suites against each declared core floor (10.6, 11.3) plus the 11 ceiling.
  Each leg asserts the resolved core version and a collected-test-count floor,
  so a support claim is exercised rather than merely declared.

### Changed

- Narrowed `core_version_requirement` to `^10.6 || ^11.3` (was `^10.2 || ^11`),
  dropping Drupal branches that are end-of-life upstream. The remaining claim is
  now tested in CI on every supported floor.

### Fixed

- The runtime requirements report (Status report) now runs on Drupal 10.x. It
  used the OOP `hook_runtime_requirements()` / `#[Hook]` form, which exists only
  on Drupal 11.1/11.2+, so its checks silently never ran on the 10.6 floor; it
  now uses the portable procedural `hook_requirements()`.

### Documentation

- README install command and Requirements updated for the stable release:
  `composer require drupal/graphql_compose_codegen`, on Drupal 10.6 or 11.3+.

## [1.0.0-rc1] — 2026-07-21

### Added

- Compatible with `graphql_compose` 3.x (GraphQL 5 / webonyx 15): the composer constraint
  is `^2.1 || ^3.0`, so the module installs and generates against both the 2.x and 3.x lines.
- `gqcc:inspect` Drush command — enumerate node bundles and their extra
  (non-base-type) fields with GraphQL names, TypeScript types, cardinality,
  and required status.
- `gqcc:generate` Drush command — scaffold four artefact types per run:
  - `types.generated.d.ts` — TypeScript type definitions
  - `fragments.generated.ts` — GraphQL inline fragments
  - `node-renderer-cases.generated.tsx` — NodeRenderer switch-case stubs
  - `components/{Name}.generated.tsx` — one React component stub per bundle
- `gqcc:diff` Drush command — compare current schema against last generation snapshot.
- `gqcc:validate` Drush command — verify scaffold files on disk are in sync with the live schema.
- Settings form at `/admin/config/development/graphql-compose-codegen`.
- `hook_help` page at `/admin/help/graphql_compose_codegen`.
- `hook_requirements` checks at `/admin/reports/status` (bundles exposed, stale base fields).
- Schema-change hooks for node and paragraph bundles/fields, with actionable log notices.
- Full paragraph bundle introspection (TypeScript types, GQL fragments, component stubs).
- Attribute-based plugin system for custom Drupal-type → TypeScript-type mappers.
- `hook_graphql_compose_codegen_pre_generate` / `_post_generate` for extensibility.
- `--dry-run`, `--allow-external`, idempotency (skip write when content unchanged), output-dir safety guard.
- Drush 12+ attribute-style commands with `AutowireTrait`.
- PHPUnit Unit + Kernel test suites.
- PHPStan and phpcs configurations.
- CI: Slack release notification (`.github/workflows/release-notify.yml`) — posts to the
  maintainers' release channel on release tags; no-ops without the `SLACK_WEBHOOK_RELEASES` secret.
- CI: Dependabot patch/minor PRs now auto-merge once checks pass (majors still
  reviewed), via the org reusable workflow
  (`.github/workflows/dependabot-automerge.yml` calls the shared
  `dependabot-automerge.yml` in `Wilkes-Liberty/.github`).
- Org governance baseline: changelog-check and changelog-autoupdate GitHub
  Actions callers, Dependabot config (composer + github-actions, weekly), and a
  non-blocking `composer audit` workflow for PHP dependency security scanning.

### Changed

- Development branch is now `1.x`, not `1.0.x`. A `1.x` branch ships every 1.y release from
  one line; `1.0.x` is patch-only for the 1.0 series. Track dev with
  `composer require 'drupal/graphql_compose_codegen:1.x-dev'`. Standardizes the branch model
  across the W&L drupal.org modules.

### Fixed

- Replaced `hook_field_storage_config_insert/delete` with `hook_field_config_insert/delete`
  so the bundle name is available when the field is attached (previously logged `[]`).
- README no longer claims an admin UI that didn't exist — the settings form now ships in this release.
