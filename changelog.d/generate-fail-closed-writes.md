---
section: Fixed
---
`gqcc:generate` prints a written/skipped/failed summary instead of an unconditional success banner, and exits non-zero when a mkdir or write fails. A skip-only run without `--overwrite` no longer claims the scaffold was written.
