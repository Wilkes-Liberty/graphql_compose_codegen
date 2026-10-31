---
section: Fixed
---
The settings form now rejects PathGuard-illegal `output_dir` values (`..`, `/etc`, `/`, paths outside the project) at save time. An empty directory remains valid (stdout / `--output-dir`).
