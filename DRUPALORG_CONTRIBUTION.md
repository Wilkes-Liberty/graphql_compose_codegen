# Contributing graphql_compose_codegen to Drupal.org

Step-by-step guide for submitting this module as a full contributed project.

---

## Overview

Drupal.org uses a two-stage process:

1. **Sandbox project** — available immediately, no review required. Anyone can
   create one. Good for early testing and getting the repo onto GitLab.
2. **Full project** — listed in the module directory, installable via Composer,
   eligible for security coverage. Requires a one-time application and review.

You will go through both stages.

---

## Prerequisites

- [ ] Drupal.org account with a verified email address.
  Create one at https://www.drupal.org/user/register if needed.
- [ ] Git installed locally (`git --version`).
- [ ] SSH key added to your Drupal.org account (see step 1c below).
- [ ] The module directory at `~/Repositories/graphql_compose_codegen/` is
  clean and contains:
  - `graphql_compose_codegen.info.yml`
  - `graphql_compose_codegen.module`
  - `graphql_compose_codegen.services.yml`
  - `drush.services.yml`
  - `composer.json`
  - `LICENSE.txt`
  - `CHANGELOG.md`
  - `.gitlab-ci.yml`
  - `config/` directory
  - `src/` directory

---

## Stage 1 — Create a Sandbox Project

### 1a. Go to the sandbox creation page

https://www.drupal.org/node/add/project-module

Log in first if prompted.

### 1b. Fill in the sandbox form

| Field | Value |
|---|---|
| **Project name** | `GraphQL Compose Codegen` |
| **Project URL** | `graphql_compose_codegen` (auto-filled, must match the module machine name) |
| **Project type** | Module |
| **Sandbox** | Yes — check "Sandbox" |
| **Description** | Drush-powered TypeScript and GraphQL scaffold generator for Next.js frontends driven by graphql_compose. Provides `gqcc:inspect` and `gqcc:generate` commands that introspect your Drupal content model and emit ready-to-use TypeScript type definitions, GraphQL fragments, a NodeRenderer switch-case file, and per-bundle React component stubs. |
| **Category** | Developer Tools |
| **Maintenance status** | Actively maintained |
| **Development status** | Under active development |

Click **Save**.

### 1c. Add your SSH key (if not done already)

1. Generate a key if needed: `ssh-keygen -t ed25519 -C "your@email.com"`
2. Copy the public key: `cat ~/.ssh/id_ed25519.pub`
3. Go to https://git.drupalcode.org/-/profile/keys
4. Click **Add new key**, paste the public key, give it a title, save.

### 1d. Clone the empty sandbox repo

After saving the sandbox, Drupal.org shows you the GitLab repo URL. It will
look like:

```
git@git.drupalcode.org:sandbox/YOUR_UID/graphql_compose_codegen.git
```

Clone it:

```bash
git clone git@git.drupalcode.org:sandbox/YOUR_UID/graphql_compose_codegen.git \
  ~/Repositories/graphql_compose_codegen_remote
```

### 1e. Push the module code

```bash
# Copy the module files into the cloned repo
rsync -av --exclude='.git' \
  ~/Repositories/graphql_compose_codegen/ \
  ~/Repositories/graphql_compose_codegen_remote/

cd ~/Repositories/graphql_compose_codegen_remote

git add -A
git commit -m "Initial commit: graphql_compose_codegen 1.0.0-dev"

# Create and push the 1.0.x branch (Drupal convention: MAJOR.MINOR.x)
git checkout -b 1.0.x
git push -u origin 1.0.x
```

> **Branch naming convention**: Drupal uses `MAJOR.MINOR.x` branches, e.g.
> `1.0.x`, `2.1.x`. The default branch should typically be your latest stable
> minor branch. Tags follow semver: `1.0.0`, `1.0.1`, etc.

### 1f. Verify CI passes

Go to your sandbox project on GitLab:
`https://git.drupalcode.org/sandbox/YOUR_UID/graphql_compose_codegen`

Open **CI/CD → Pipelines** and confirm the pipeline goes green. The `.gitlab-ci.yml`
included in this repo uses the standard Drupal CI template which runs PHPStan,
phpcs (Drupal coding standards), and PHPUnit.

Fix any failures before applying for a full project.

---

## Stage 2 — Apply for a Full Project

Once your sandbox has code, CI is green, and the module is reasonably complete:

### 2a. Review the requirements

Read the full guidelines before applying:
https://www.drupal.org/docs/develop/contributing-to-drupal/contributed-projects/applying-for-a-full-project-on-drupal-org

Key requirements:
- Module must work and be useful to others.
- Must follow Drupal coding standards (checked by CI).
- Must use GPL-2.0-or-later license. ✓ (already set)
- Must not duplicate an existing module without clear differentiation.
- README must explain what the module does and how to use it. ✓

### 2b. Submit the application

1. Go to: https://www.drupal.org/node/add/project-issue/projectapplications
2. Fill in:
   - **Title**: `[module application] graphql_compose_codegen`
   - **Category**: Module application
   - **Description**: Explain what the module does, who it's for, and how it
     differs from any similar modules. Include a link to your sandbox.

   Example description:

   > **graphql_compose_codegen** provides two Drush commands (`gqcc:inspect`
   > and `gqcc:generate`) that introspect a Drupal site's node content model
   > via graphql_compose and emit four scaffold artefacts for a Next.js
   > frontend: TypeScript type definitions, GraphQL inline fragments, a
   > NodeRenderer switch-case file, and per-bundle React component stubs.
   >
   > There is no existing module that performs this code generation step in
   > the graphql_compose ecosystem. The closest is graphql_compose itself,
   > which exposes the schema but leaves consumers to write their own
   > TypeScript by hand.
   >
   > Sandbox: https://www.drupal.org/sandbox/YOUR_USERNAME/graphql_compose_codegen
   > GitLab: https://git.drupalcode.org/sandbox/YOUR_UID/graphql_compose_codegen

3. Submit the issue.

### 2c. Respond to reviewer feedback

A community reviewer will look at your code and may request changes. Common
requests:
- Add tests (KernelTest or FunctionalTest).
- Fix coding standards violations.
- Clarify the README.
- Rename confusingly similar class or service names.

Respond in the issue and push fixes to your sandbox branch.

### 2d. Project approved — transfer to full namespace

When approved, Drupal.org staff will:
1. Create the full project at `drupal.org/project/graphql_compose_codegen`.
2. Create a GitLab repo at `git.drupalcode.org/project/graphql_compose_codegen`.
3. Notify you to push your code to the new repo.

Follow their instructions to re-add the new remote and push:

```bash
cd ~/Repositories/graphql_compose_codegen_remote

git remote add drupal \
  git@git.drupalcode.org:project/graphql_compose_codegen.git

git push drupal 1.0.x
```

---

## Stage 3 — Tag a Release

Once the full project exists and CI is green:

```bash
cd ~/Repositories/graphql_compose_codegen_remote

# Tag the first alpha or stable release
git tag 1.0.0
git push drupal 1.0.0
```

Then on drupal.org:
1. Go to your project page → **Releases** → **Add new release**.
2. Select the tag `1.0.0`.
3. Set release type (Alpha / Beta / RC / Full release).
4. Write release notes (copy from CHANGELOG.md).
5. Publish.

---

## Ongoing Maintenance

- **Issues**: Users will file issues at
  `drupal.org/project/issues/graphql_compose_codegen`.
  Monitor via email notifications (set in your drupal.org account settings).

- **Security**: Once you have a full project, you can opt into the
  Drupal Security Team's coverage. See:
  https://www.drupal.org/security/security-advisory-process-and-permissions-policy

- **Compatibility updates**: When new Drupal core versions release, update
  the `core_version_requirement` in `graphql_compose_codegen.info.yml` and
  push a new patch tag (e.g. `1.0.1`).

- **Composer installability**: After the full project is live and has a
  release tag, users can install via:
  ```bash
  composer require drupal/graphql_compose_codegen
  ```

---

## Quick Reference — Key URLs

| Resource | URL |
|---|---|
| Create sandbox | https://www.drupal.org/node/add/project-module |
| SSH keys | https://git.drupalcode.org/-/profile/keys |
| Full project guidelines | https://www.drupal.org/docs/develop/contributing-to-drupal/contributed-projects/applying-for-a-full-project-on-drupal-org |
| Module application queue | https://www.drupal.org/project/issues/projectapplications |
| Drupal coding standards | https://www.drupal.org/docs/develop/standards |
| GitLab CI templates | https://git.drupalcode.org/project/gitlab_templates |
