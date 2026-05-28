# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses Drupal-style version tags (`1.0.0`, `1.0.1`, etc.).

---

## [1.0.0-dev] — Unreleased

### Added

- `gqcc:inspect` Drush command — enumerate node bundles and their extra
  (non-base-type) fields with GraphQL names, TypeScript types, cardinality,
  and required status.
- `gqcc:generate` Drush command — scaffold four artefact types per run:
  - `types.generated.d.ts` — TypeScript type definitions
  - `fragments.generated.ts` — GraphQL inline fragments
  - `node-renderer-cases.generated.tsx` — NodeRenderer switch-case stubs
  - `components/{Name}.generated.tsx` — one React component stub per bundle
- `SchemaInspector` service — field discovery, GQL/TS name resolution,
  `entity_reference` / `entity_reference_revisions` target-type mapping,
  multi-value cardinality detection.
- `TypeScriptGenerator` service — template-based code generation for all
  four artefact types.
- Module configuration schema (`graphql_compose_codegen.settings`) with
  configurable `base_type_fields` and `output_dir` defaults.
- Support for `drupal/scheduler` fields (`publish_on`, `unpublish_on`) via
  automatic `timestamp` → `string` type mapping (suggested dependency).
- Lazy `\Drupal::service()` accessor pattern so commands work under Drush 12
  and Drush 13's `LegacyServiceInstantiator` discovery without constructor
  arguments on the command class.
- Full `FIELD_TYPE_MAP` covering text, number, boolean, date, link, image,
  file, list, address, geolocation, range, and color field types.
- `--bundles`, `--output-dir`, `--overwrite`, and `--skip-fields` options
  on both commands.
- Stdout mode (no `--output-dir`) with bordered file separators for quick
  review or pipe-to-file workflows.
- Drupal 10.2+ and Drupal 11 compatibility.
