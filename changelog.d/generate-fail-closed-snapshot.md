---
section: Fixed
---
`gqcc:generate` no longer replaces `graphql_compose_codegen.last_snapshot` when a mkdir or write fails. `gqcc:diff` and the MCP diff tool keep the last successful baseline.
