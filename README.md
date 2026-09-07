# GraphQL Compose Codegen

A Drush-powered TypeScript and GraphQL scaffold generator for Next.js frontends
driven by [graphql_compose](https://www.drupal.org/project/graphql_compose).

When you add or remove a content type or field in Drupal, this module tells you
what needs updating in your Next.js project and generates the boilerplate so
you can focus on the actual component logic.

Built by **[Jeremy Michael Cerda](https://www.drupal.org/u/jmcerda)** (jmcerda@wilkesliberty.com). Maintained by [Wilkes & Liberty, LLC](https://github.com/Wilkes-Liberty).

## Features

- **`drush gqcc:inspect`** — list every node (and paragraph) bundle and its "extra" fields (fields not already in your shared base type).
- **`drush gqcc:generate`** — write four scaffold artefacts:
  - `types.generated.d.ts` — TypeScript type additions for `types/index.d.ts`
  - `fragments.generated.ts` — GraphQL inline fragments for `lib/queries/node-by-path.ts`
  - `node-renderer-cases.generated.tsx` — switch-case stubs for `NodeRenderer.tsx`
  - `components/{Name}.generated.tsx` — one bare React component stub per bundle

  Paragraph bundles get the same four artefact types under `paragraphs/`:
  - `paragraphs/types.generated.d.ts` — types joining the `DrupalParagraph` union
  - `paragraphs/fragments.generated.ts` — the `PARAGRAPH_FRAGMENTS` block
  - `paragraphs/paragraph-renderer-cases.generated.tsx` — switch-case stubs for `ParagraphRenderer.tsx`
  - `paragraphs/components/{Name}Paragraph.generated.tsx` — one component stub per bundle
- **`drush gqcc:diff`** — compare the current schema against the last generation snapshot.
- **`drush gqcc:validate`** — verify scaffold files on disk are in sync with the live schema (useful in CI / pre-commit).
- **Schema-change hooks** — logs a Drupal notice at `/admin/reports/dblog` whenever a node or paragraph bundle / field is created or deleted, with the exact `drush gqcc:generate` command to run.
- **Pluggable field-type mappers** — other modules can register their own Drupal-type → TypeScript-type mappings via tagged plugins.

## Requirements

- Drupal 10.6, or 11.3 and up
- `graphql_compose` 2.1 or higher
- Drush 12 or 13
- PHP 8.1+

### Why Drush is a hard dependency

This module's entire user surface is Drush commands. The `drush/drush` package
is in `require` (not `suggest`) because without it the module does nothing
useful. If you cannot ship Drush in your project's `require` block, do not
install this module.

## Installation

```bash
composer require drupal/graphql_compose_codegen
drush en graphql_compose_codegen
```

## Configuration

Configure via `drush config:set` or by editing
`graphql_compose_codegen.settings.yml` in your config sync directory:

Or visit **Configuration → Development → GraphQL Compose Codegen**
(`/admin/config/development/graphql-compose-codegen`) to edit the same
settings via the admin UI. Both methods write to the same
`graphql_compose_codegen.settings` config object.

```yaml
# config/sync/graphql_compose_codegen.settings.yml
base_type_fields:
  - title
  - path
  - body
  - field_summary
  # ... add any project-specific common fields
base_ts_type: NodeCommonFields
output_dir: '../ui'   # relative to Drupal root — or use an absolute path
```

| Key | Default | Description |
|-----|---------|-------------|
| `base_type_fields` | `[title, path, body, field_summary, …]` | Fields in your shared base TypeScript type. Excluded from per-bundle output. |
| `base_ts_type` | `NodeCommonFields` | Name of the shared base TS type. |
| `output_dir` | _(empty)_ | Default output directory. Can be overridden with `--output-dir`. |

## Workflow

### Inspecting the schema

```bash
drush gqcc:inspect
drush gqcc:inspect --bundles=platform,service
```

### Generating scaffold files

```bash
# Print all scaffold artefacts to stdout (review before writing):
drush gqcc:generate

# Write scaffold to ../ui/generated/ (relative to Drupal root):
drush gqcc:generate --output-dir=../ui

# Scaffold only new bundles:
drush gqcc:generate --bundles=newsletter,event --output-dir=../ui

# Regenerate even if scaffold files already exist:
drush gqcc:generate --output-dir=../ui --overwrite

# Skip fields that are being handled elsewhere:
drush gqcc:generate --skip-fields=field_components,field_paragraphs

# Preview without writing:
drush gqcc:generate --output-dir=../ui --dry-run
```

### Integrating the scaffold

After running `drush gqcc:generate --output-dir=../ui`, the generated files
land in `../ui/generated/`. Integrate them manually:

1. **`types.generated.d.ts`** — copy each new `export type Drupal*` block into
   `types/index.d.ts`, and add the new type name to the `DrupalNode` union.
2. **`fragments.generated.ts`** — copy each `... on NodeFoo { }` block into the
   `NODE_BY_PATH_QUERY` template literal in `lib/queries/node-by-path.ts`.
3. **`node-renderer-cases.generated.tsx`** — add the import line and switch case
   for each bundle into `components/drupal/NodeRenderer.tsx`.
4. **`components/{Name}.generated.tsx`** — rename to `{Name}.tsx`, move to
   `components/drupal/`, and implement the actual component layout.
5. **`paragraphs/*`** — same four steps for paragraph bundles: types join the
   `DrupalParagraph` union, fragments form the `PARAGRAPH_FRAGMENTS` template
   literal, renderer cases go into
   `components/drupal/paragraphs/ParagraphRenderer.tsx`, and component stubs
   move to `components/drupal/paragraphs/`.

The scaffold files in `generated/` are intentionally suffixed `.generated`
and not referenced anywhere. Delete them once you have integrated the code
you need.

### Paragraph specifics

- Only paragraph bundles enabled in graphql_compose (`enabled` +
  `query_load_enabled` in `entity_config`) are inspected and generated, and
  only their graphql_compose-enabled fields. Sites without any
  graphql_compose config fall back to listing everything.
- Paragraph references between paragraphs (`entity_reference_revisions`)
  emit nested inline fragments for the allowed target bundles, one level
  deep. Bundles that only appear nested (e.g. FAQ items inside an FAQ group)
  get no top-level fragment or renderer case of their own.
- When two bundles select the same field name with incompatible shapes
  (required vs optional sub-fields), GraphQL refuses to merge the shared
  response key across the union, so the generator aliases the later bundle's
  field — e.g. `tabItems: items` — and names the TypeScript property after
  the alias.

### Drift detection in CI

```bash
# Exits non-zero if scaffold files on disk are out of sync with the live schema.
drush gqcc:validate --output-dir=../ui
```

### Automatic notifications

When a content editor or developer creates a new node or paragraph bundle, or
adds/removes a field via the Drupal admin UI, the module logs a structured
notice at `/admin/reports/dblog` with the exact command to run.

## Comparison to existing modules

| Module | What it does | How `graphql_compose_codegen` differs |
|---|---|---|
| [typescript_generator](https://www.drupal.org/project/typescript_generator) | TS types for Drupal entity *internals* (`FieldItemList<IntegerItem>` style). | This module emits the *client-facing* shape that graphql_compose actually returns over the wire. |
| [nextgen](https://www.drupal.org/project/nextgen) | Next.js component/page scaffolding from **JSON:API** via Drush Code Generator. | This module is graphql_compose-aware (not JSON:API) and emits TypeScript types + fragments alongside components. |
| [graphql_compose_fragments](https://www.drupal.org/project/graphql_compose_fragments) | GraphQL fragments exposed inside the schema's `_info` query (runtime, server-side). | This module emits fragments to disk for direct merge into hand-written queries, plus TypeScript types and React stubs. |
| [graphql_export](https://www.drupal.org/project/graphql_export) | Exports raw `.graphqls` / introspection JSON to disk for npm `graphql-codegen`. | Complementary, not a duplicate. This module is opinionated for the graphql_compose + Next.js stack and emits ready-to-merge code, not raw schema. |

This module is intentionally narrow: graphql_compose + Next.js + React. If
you're on a different stack, the modules above may serve you better.

## Architecture

```
graphql_compose_codegen/
├── graphql_compose_codegen.module         # hooks: bundle_create/delete, field_config_*, help, requirements
├── graphql_compose_codegen.services.yml   # service + plugin manager registrations
├── graphql_compose_codegen.routing.yml    # settings form route
├── graphql_compose_codegen.links.menu.yml # menu entry under Config → Development
├── graphql_compose_codegen.permissions.yml
├── graphql_compose_codegen.api.php        # hook documentation
├── config/
│   ├── install/graphql_compose_codegen.settings.yml
│   └── schema/graphql_compose_codegen.schema.yml
└── src/
    ├── Attribute/FieldTypeMapper.php       # plugin attribute
    ├── Drush/Commands/CodegenCommands.php  # Drush 12+ attribute commands
    ├── Form/SettingsForm.php
    ├── Plugin/FieldTypeMapper/             # default field-type mapper plugins
    ├── PluginManager/FieldTypeMapperManager.php
    └── Service/
        ├── SchemaInspector.php
        ├── TypeScriptGenerator.php
        ├── ArtefactSnapshot.php
        └── PathGuard.php
```

## Extending the field-type mapper

Other modules can register custom mappers via the attribute-based plugin
system. See `graphql_compose_codegen.api.php` for `hook_graphql_compose_codegen_pre_generate`
/ `hook_graphql_compose_codegen_post_generate` documentation and the
`#[FieldTypeMapper]` attribute.

## Roadmap (v1.1+)

These were considered for v1.0 and deferred:

- Runtime validation output (Zod / Valibot / Yup `.schema.ts` files)
- `gqcc:watch` daemon mode
- Storybook / Vitest stub generation per bundle
- Vue / Svelte / Astro / Solid component variants (would require a template plugin system)
- Schema.org Blueprints integration

Issues and merge requests at
[drupal.org/project/graphql_compose_codegen](https://www.drupal.org/project/graphql_compose_codegen).

## License

GPL-2.0-or-later, as per Drupal community standards.
