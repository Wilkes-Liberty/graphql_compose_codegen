# GraphQL Compose Codegen

A Drush-powered TypeScript and GraphQL scaffold generator for Next.js frontends
driven by [graphql_compose](https://www.drupal.org/project/graphql_compose).

When you add or remove a content type or field in Drupal, this module tells you
what needs updating in your Next.js project and generates the boilerplate so
you can focus on the actual component logic.

## Features

- **`drush gqcc:inspect`** — list every node bundle and its "extra" fields (fields not already in your shared base type).
- **`drush gqcc:generate`** — write four scaffold artefacts:
  - `types.generated.d.ts` — TypeScript type additions for `types/index.d.ts`
  - `fragments.generated.ts` — GraphQL inline fragments for `lib/queries/node-by-path.ts`
  - `node-renderer-cases.generated.tsx` — switch-case stubs for `NodeRenderer.tsx`
  - `components/{Name}.generated.tsx` — one bare React component stub per bundle
- **Schema-change hooks** — logs a Drupal notice (visible at `/admin/reports/dblog`) whenever a node bundle or field is created or deleted, with the exact `drush gqcc:generate` command to run.

## Requirements

- Drupal 10 or 11
- `graphql_compose` module enabled
- Drush 12+

## Installation

```bash
composer require drupal/graphql_compose_codegen
drush en graphql_compose_codegen
```

## Configuration

Navigate to **Configuration → Development → GraphQL Compose Codegen** (or edit
config directly):

| Key | Default | Description |
|-----|---------|-------------|
| `base_type_fields` | `[title, path, body, field_summary, …]` | Fields in your shared base TypeScript type. Excluded from per-bundle output. |
| `base_ts_type` | `NodeCommonFields` | Name of the shared base TS type. |
| `output_dir` | _(empty)_ | Default output directory for generated files. Can be overridden with `--output-dir`. |

Override the base type field list via `drush config:set` or by editing
`graphql_compose_codegen.settings` in your config management workflow:

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

## Workflow

### Inspecting the schema

```bash
drush gqcc:inspect
drush gqcc:inspect --bundles=platform,service
```

This shows every node bundle with its extra fields (name, GQL field name,
TypeScript type) so you can see at a glance whether your TS files are current.

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

The scaffold files in `generated/` are intentionally ignored by TypeScript
(they have a `.generated` suffix and are not referenced anywhere). Delete them
once you have integrated the code you need.

### Automatic notifications

When a content editor or developer creates a new node bundle or adds/removes a
field via the Drupal admin UI, the module logs a structured notice at
`/admin/reports/dblog` with the exact command to run. No polling; it fires via
Drupal entity lifecycle hooks.

## Architecture

```
graphql_compose_codegen/
├── graphql_compose_codegen.module         # hooks: bundle_create/delete, field_storage_*
├── graphql_compose_codegen.services.yml   # service registrations
├── config/
│   ├── install/graphql_compose_codegen.settings.yml
│   └── schema/graphql_compose_codegen.schema.yml
└── src/
    ├── Commands/CodegenCommands.php       # Drush commands (gqcc:inspect, gqcc:generate)
    └── Service/
        ├── SchemaInspector.php            # bundle/field introspection + type mapping
        └── TypeScriptGenerator.php        # artefact generation (types, fragments, stubs)
```

### Field type mapping

`SchemaInspector::FIELD_TYPE_MAP` maps Drupal field type plugin IDs to TypeScript
strings. Entity reference fields are resolved by `target_type` (taxonomy_term →
`TaxonomyTermRef`, node → `RelatedNode`, media → `DrupalMedia`, paragraph →
`DrupalParagraph[]`). Unknown types are flagged with `unknown` so you can
handle them manually.

### Base type fields

Fields in `base_type_fields` config are excluded from per-bundle output because
they already belong to the shared TypeScript type. This mirrors the
`NodeCommonFields` pattern used by `next-drupal` and `graphql_compose_next`.

## Contributing

Issues and merge requests welcome at
[drupal.org/project/graphql_compose_codegen](https://www.drupal.org/project/graphql_compose_codegen).

## License

GPL-2.0-or-later, as per Drupal community standards.
