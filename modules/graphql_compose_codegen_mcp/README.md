# Governed schema tools

This optional module exposes Codegen through Tool API. The base Codegen module
continues to work without Sentinel or Tool API. Install MCP Sentinel 2.22.0 or
later, then enable `graphql_compose_codegen_mcp`.

## Contract

| Plugin ID | Inputs | Outputs | Result |
| --- | --- | --- | --- |
| `graphql_compose_codegen_inspect` | `bundles[]`, `skip_fields[]` | `nodes`, `paragraphs` | Node field descriptors and paragraph alias map |
| `graphql_compose_codegen_diff` | `bundles[]`, `skip_fields[]` | `has_snapshot`, `changes` | Snapshot presence and added, changed, removed paths |
| `graphql_compose_codegen_preview` | `bundles[]`, `skip_fields[]` | `artefacts` | Generated artefacts keyed by relative path |

Each tool accepts optional `bundles` and `skip_fields` arrays of machine names.
An empty bundle array selects all enabled bundles. Unknown, duplicate, malformed,
or oversized selectors fail. There is no output path, write, overwrite, or
snapshot-update argument. Preview uses the same `buildArtefacts()` pipeline as
Drush. Existing Drush commands remain available.

The complete dependency set is limited to 64 enabled node and paragraph bundles,
with at most 256 field definitions per bundle. Paragraph aliases depend on all
enabled paragraph bundles, so a subset cannot bypass this limit. Selectors allow
64 bundle names and 256 skipped field names. Results must fit in 262,144 encoded
JSON bytes and the caller's stricter Sentinel response budget. Oversized results
fail instead of silently truncating generated code.

## Access

Require both `access mcp sentinel context` and
`administer graphql_compose_codegen`, an eligible Sentinel policy with config
read enabled, and the exact OAuth scope `mcp_config_read`. Sentinel's source
readiness, audit wiring, IP restrictions, rate budget, and DLP checks apply.
Discovery evaluates the same source policy without fabricated tool inputs.
Direct PHP execution repeats the access check. Anonymous execution is refused.
A content-tier token that holds only `mcp_read` is the wrong scope.

If a policy denies any configuration dependency family (`graphql_compose*`,
`field.field.*`, `field.storage.*`, `node.type.*`, or
`paragraphs.paragraphs_type.*`), the tools refuse the whole operation. Removing
parts of the schema would produce an incorrect scaffold.

Register the plugins in the site's MCP Tool API bridge with authentication
required and `mcp_config_read`; registration belongs to site configuration.
Enabling this module does not grant permissions or publish unauthenticated tools.

Preview never invokes file-writing commands, generation lifecycle hooks, or
snapshot recording. The diff reports a missing baseline explicitly. Failures
exclude schema values, mapper exception details, and local paths.

Issue: https://www.drupal.org/project/graphql_compose_codegen/issues/3623734
