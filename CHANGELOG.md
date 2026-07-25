# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses Drupal-style version tags (`1.0.0`, `1.0.1`, etc.).

---

## [Unreleased]

## [1.1.0] — 2026-07-25

### Added

- `gqcc:inspect` now lists paragraph bundles alongside node bundles, in the
  same visual style, with GraphQL/TypeScript names, aliased response keys,
  and a nested-only marker for bundles that render inside their parents
  (issue [#3613218](https://www.drupal.org/project/graphql_compose_codegen/issues/3613218)).
- New paragraph artefact: `paragraphs/paragraph-renderer-cases.generated.tsx`
  — switch-case stubs for a `ParagraphRenderer` component (switch on
  `__typename`, `data` prop, `default: return null`). Paragraph bundles now
  get the same four artefact types as node bundles.
- Response-key collision handling: when two paragraph bundles select the same
  field name with incompatible shapes (e.g. required vs optional sub-fields —
  `String!` vs `String`), GraphQL forbids merging the shared response key
  across the union, so the generator aliases the later bundle's field
  (`tabItems: items`) and keeps the TypeScript property name aligned with
  the alias. Aliases are computed across all enabled bundles, so `--bundles`
  subset runs emit the same names as a full run.
- Nested paragraph references (`entity_reference_revisions` targeting
  paragraphs) now emit nested inline fragments for the allowed target
  bundles (`handler_settings.target_bundles`), one level deep, and
  bundle-specific TypeScript types (`DrupalParagraphPFaqItem[]` instead of
  the whole `DrupalParagraph` union). Previously the fragment emitted a
  self-referential `${PARAGRAPH_FRAGMENTS}` placeholder inside the
  `PARAGRAPH_FRAGMENTS` literal itself.
- graphql_compose awareness: paragraph bundles are filtered by
  `entity_config` (`enabled` + `query_load_enabled`) and paragraph fields by
  `field_config` enablement, supporting both the 2.x config name
  (`graphql_compose.settings`) and 3.x per-server names
  (`graphql_compose.settings.<server_id>`). Without any graphql_compose
  config the previous list-everything behaviour is kept.
- `paragraph_bundles` key in the pre/post-generate hook `$context`.
- WebformMapper: the contrib `webform` field type now maps to a
  `DrupalWebform` TypeScript type with the full graphql_compose_webform
  selection (`id label description elements { … options { id value } }`)
  instead of `unknown` with a TODO selector. The `DrupalWebform` helper
  types are appended to the types artefact when a webform field is present
  (issue [#3613232](https://www.drupal.org/project/graphql_compose_codegen/issues/3613232)).

### Changed

- `--bundles` now filters paragraph bundles too, for `gqcc:generate`,
  `gqcc:diff`, `gqcc:validate`, and `gqcc:inspect`. A paragraph-only
  `--bundles` run no longer aborts with "No matching node bundles found"
  (and no longer emits empty node artefacts over real ones).
- Paragraph component stubs use the `data` prop and `<Bundle>Paragraph`
  naming (`FaqGroupParagraph.generated.tsx`), matching ParagraphRenderer
  conventions; previously they used a `node` prop and `ParagraphPFaqGroup`
  naming.
- Generated fields are emitted in machine-name order so output is
  deterministic across environments (field-definition order is not).
- The schema-change log notices now emit a working command for paragraph
  bundles (`--bundles=<bundle> --overwrite`) and name the correct renderer
  (ParagraphRenderer) on paragraph bundle deletion.

### Fixed

- PathGuard now rejects the macOS `/private` variants of the dangerous
  filesystem roots. On macOS, `/etc`, `/tmp` and `/var` are symlinks into
  `/private`, so `realpath('/etc')` resolves to `/private/etc` and slipped
  past the `--output-dir` dangerous-root check on Mac hosts (issue
  [#3613237](https://www.drupal.org/project/graphql_compose_codegen/issues/3613237)).

### Tests

- Kernel fixture reproducing the response-key merge trap (optional
  `p_faq_item` vs required `p_tab_item` title/body under a shared `items`
  field) plus coverage for nesting, artefact-set composition, filtering, and
  graphql_compose config handling. `drupal/paragraphs` added to require-dev
  and both CI pipelines; the GitHub Actions collected-test floor raised from
  20 to 30.
- Kernel coverage for the schema-change log notices: the suggested command
  in each notice (bundle create, bundle delete, field insert) is asserted
  verbatim, so a tip that would silently generate nothing fails the suite.

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
