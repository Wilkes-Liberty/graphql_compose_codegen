# Contributing to GraphQL Compose Codegen

Thanks for your interest. This module is maintained by a single developer
(Jeremy Cerda), so response times will vary. Bug reports, feature requests,
test coverage, and new field-type mapper plugins are all welcome.

If you're new to contributing on drupal.org, the
[Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal)
guide is the canonical reference for the workflow described below.

---

## Filing issues

All bugs, feature requests, and support questions live in the drupal.org
issue queue:

**<https://www.drupal.org/project/issues/graphql_compose_codegen>**

Before filing a new issue:

- Search the queue (open + closed) for duplicates.
- Pick the right issue type: **Bug report**, **Feature request**, **Task**, or
  **Support request**.
- Set **Version** to `1.0.x-dev` unless the bug is tied to a specific tagged
  release.
- For bugs, provide a minimal reproduction: the relevant `graphql_compose`
  schema configuration, the bundle / field setup, the command you ran, and
  the full output (or the diff between expected and actual output).

---

## Merge request workflow

Drupal.org uses GitLab-hosted merge requests. The flow is the same as Drupal
core's:

1. **File or claim an issue** first. MRs without a linked issue are rarely
   reviewed.
2. **Click "Create issue fork"** on the issue page. Drupal.org GitLab creates
   a personal fork and an `issue/graphql_compose_codegen-<issue_number>`
   branch for you automatically.
3. **Push your commits** to that branch (locally or via the GitLab Web IDE).
   Target the project's `1.0.x` branch.
4. **Open a merge request** from your issue branch into
   `graphql_compose_codegen:1.0.x`. The MR title format is:

   ```
   Issue #<number>: <Short description>
   ```

5. **Set the issue status to "Needs review"** once your MR is ready.

For tiny patches (typo fixes, doc corrections), a direct MR without lengthy
issue discussion is fine — just make sure an issue exists so the MR has
somewhere to thread.

---

## Branch conventions

| Branch | Purpose |
|---|---|
| `1.0.x` | Current working trunk. All development targets this branch. |

There is no `master` or `main` branch on origin — this matches the convention
of every active Drupal contrib project (graphql_compose, paragraphs, webform,
token all use `MAJOR.MINOR.x` as the default branch). When a `2.0.x` line
opens (breaking changes, new Drupal major support), a new branch will be
created from the tip of `1.0.x`; backports to `1.0.x` will be accepted on a
case-by-case basis.

---

## Coding standards

The project follows Drupal core coding standards (`Drupal` + `DrupalPractice`)
plus a small set of project conventions you'll see throughout the codebase.

### Required style

- **`declare(strict_types=1);`** on its own line, immediately after the file
  `@file` docblock, with blank lines above and below.
- **Type hints on everything new.** Parameter types, return types, no
  `mixed`, no `stdClass`. Prefer interfaces over concrete classes in
  signatures.
- **`final` classes by default.** Plugin classes and services are `final`
  unless there's an explicit reason to allow subclassing.
- **Attribute-based hooks.** New hook implementations live in a class under
  `src/Hook/` and use `#[Hook('hook_name')]`. Function-style
  `mymodule_hook_name()` is only acceptable for legacy hooks not yet ported
  in core.
- **2-space indent, no tabs, 80-character soft line limit.**

### Running phpcs

```bash
./vendor/bin/phpcs --standard=Drupal,DrupalPractice <path-to-module>
```

Auto-fix what's auto-fixable:

```bash
./vendor/bin/phpcbf --standard=Drupal,DrupalPractice <path-to-module>
```

The project ships a `phpcs.xml.dist` so running `phpcs` from the module root
applies the right standards automatically.

### Running PHPStan

```bash
./vendor/bin/phpstan analyse -c phpstan.neon.dist
```

---

## Running tests locally

The test suite is PHPUnit, split into Unit tests (no Drupal bootstrap) and
Kernel tests (boots the container, installs the module, creates fixture
bundles).

### Dev dependencies

You need `drupal/core-dev` installed at the project level so PHPUnit and its
Drupal-specific bootstrap are on disk:

```bash
composer require --dev drupal/core-dev
```

### Environment

Kernel tests need a database. Any of the supported drivers work; SQLite is
simplest:

```bash
export SIMPLETEST_DB="sqlite://localhost//tmp/gqcc-test.sqlite"
```

### Run everything

From the **Drupal docroot** (the directory that contains `core/`):

```bash
../vendor/bin/phpunit -c core \
  modules/contrib/graphql_compose_codegen/tests
```

Or just the unit tests (no DB required):

```bash
../vendor/bin/phpunit -c core \
  modules/contrib/graphql_compose_codegen/tests/src/Unit
```

CI runs the same suite via the shared GitLab CI template on every push and
every MR. A green pipeline on your MR branch significantly speeds up review.

---

## Adding a new field-type mapper plugin

The most common contribution surface is a new `FieldTypeMapper` plugin — a
class that tells the generator how a particular Drupal field type maps to a
TypeScript type string.

### Location

```
src/Plugin/FieldTypeMapper/
```

### Skeleton

```php
<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps Drupal foo fields to TypeScript.
 */
#[FieldTypeMapper(
  id: 'foo',
  drupalTypes: ['my_foo', 'my_other_foo'],
)]
final class FooMapper extends FieldTypeMapperBase {

  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return 'Foo';
  }

}
```

The `drupalTypes` array on the attribute is the list of Drupal field-type
plugin IDs this mapper claims. `FieldTypeMapperBase::map()` automatically
wraps multi-cardinality fields with `[]`.

### Tests

At minimum, add a Kernel test that:

1. Installs a node bundle with the target field type attached.
2. Resolves the `graphql_compose_codegen.schema_inspector` service.
3. Calls `getFieldsForBundle('demo')` and asserts `ts_type` matches the
   expected TypeScript string.

See `tests/src/Kernel/SchemaInspectorKernelTest.php` for the pattern.

### Checklist before opening the MR

- [ ] Plugin class is `final` and uses strict types.
- [ ] Plugin uses the `#[FieldTypeMapper]` attribute, not annotations.
- [ ] Kernel test covers the happy path.
- [ ] phpcs passes.
- [ ] MR description explains which Drupal field type(s) this covers and
      links to any relevant module pages (e.g. the contrib module that
      provides the field type).

---

## Commit messages

Inside an MR branch, use whatever commit style you find comfortable —
Conventional Commits, plain prose, whatever. The maintainer will squash MR
commits at merge time into a single Drupal-style commit:

```
Issue #<number> by <user1>, <user2>: <Short summary.>
```

This is the standard format Drupal core and most contrib projects use, and
it's what shows up in the merged history on `1.0.x`.

If your MR has multiple distinct logical changes that should land as separate
commits, mention that in the MR description and the maintainer will preserve
the structure.

---

## Release process (for reference)

Releases are tagged from `1.0.x` and pushed to origin. The maintainer handles
this; contributors don't need to. Documenting it here for transparency:

```bash
# 1. Confirm we're on a clean 1.0.x at the tip.
git checkout 1.0.x
git pull origin 1.0.x

# 2. Tag (annotated).
git tag -a 1.0.0-alpha3 -m "Brief description of the release"

# 3. Push the tag.
git push origin 1.0.0-alpha3
```

Then create the release node on drupal.org at
`/project/graphql_compose_codegen/releases` → Add new release → select Tag →
choose the new tag → write release notes → Save. The packager builds the
tarball within ~30 minutes.

---

## Questions

Open an issue in the queue. There's no chat channel yet. The queue is
checked at least weekly.

---

## Appendix: Maintainer notes

This section is a reference for the maintainer's own day-to-day workflow.
Contributors are welcome to read it but don't need to follow it.

### One trunk: `1.0.x`

All development happens on `1.0.x`. There is no `master`. The drupal.org
packager watches `1.0.x` and auto-rebuilds the `1.0.x-dev` release within
~15–30 minutes of every push.

If you want a `master`-style local muscle-memory alias without affecting the
remote, create a **local-only** branch that tracks `origin/1.0.x`:

```bash
git branch master --track origin/1.0.x
```

This `master` exists only in the local repo. It fast-forwards on
`git pull master` and is invisible to drupal.org. Never push it.

### Daily edit-commit-push cycle

```bash
# Make changes.

# Lint.
./vendor/bin/phpcs --standard=Drupal,DrupalPractice src tests *.module

# Test.
SIMPLETEST_DB="sqlite://localhost//tmp/gqcc-test.sqlite" \
  ../vendor/bin/phpunit -c core \
  modules/contrib/graphql_compose_codegen/tests

# Stage specific files (never `git add -A` blindly — secrets risk).
git add <files>
git commit -m "feat(generator): brief summary"

# Push.
git push origin 1.0.x
```

After the push, verify the dev release rebuilt by checking the "Last updated"
timestamp at
`https://www.drupal.org/project/graphql_compose_codegen/releases/1.0.x-dev`.
Allow up to 30 minutes for the packager.

### Tagging a release

```bash
git checkout 1.0.x
git pull origin 1.0.x
git tag -a 1.0.0-alphaN -m "Brief description"
git push origin 1.0.0-alphaN
```

Then create the release node on drupal.org pointing at the new tag.

### Rolling back

Prefer forward fixes — push a new commit that corrects the problem. Force-push
to `1.0.x` only if:

1. A commit contained a secret (API key, credential).
2. A commit contained AI attribution or other metadata that must be removed.

In either case, coordinate with the drupal.org security team before
force-pushing a branch that has an associated tagged release.

### Verifying a push reached drupal.org

```bash
git ls-remote origin 1.0.x   # tip SHA on remote
git rev-parse HEAD            # tip SHA locally
# Should match.
```

If the dev release timestamp hasn't advanced 45 minutes after a confirmed
push, post in the `#infrastructure` channel on Drupal Slack.

### Starting a `2.0.x` line later

When a breaking-change major release is needed:

```bash
git checkout -b 2.0.x 1.0.x
git push -u origin 2.0.x
```

Then update the default branch on drupal.org's GitLab project settings to
`2.0.x`. Keep `1.0.x` open for security and critical bug backports for at
least one release cycle.
