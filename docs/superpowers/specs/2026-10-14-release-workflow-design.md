# Release workflow design

Date: 2026-10-14
Branch: `improve-build-process`

## Goal

Replace the manual release build (edit `package.json#version`, run `npm run build` locally) with:

1. A single local command, `npm run release -- [version]`, that sets the version everywhere and builds the zip.
2. A GitHub Actions workflow, `.github/workflows/create_release.yml`, that runs that command on a clean runner, commits the build output to a
   `release/vX.Y.Z` branch, opens a PR to `master`, and uploads the zip as an artifact.

Success: a release is one click in the Actions tab (or one local command), produces the same zip
contents as today, and leaves a reviewable `Build vX.Y.Z` PR instead of a hand-made commit.

## Context

- Single long-lived branch: `master`. No `develop`, no RC flow, no Pro/Lite split, no private
  Composer deps.
- The version currently lives in four places:
  - `package.json`: edited by hand.
  - `wp-mail-logging.php` header: rewritten by gulp `replace_plugin_file_ver`.
  - `readme.txt` `Stable tag:`: edited by hand.
  - `package-lock.json`: never updated (still says 1.14.0).
- `{VERSION}` placeholders in `@since` / `@deprecated` are replaced by gulp `replace_since_ver`.
- The changelog lives in `readme.txt` under `== Changelog ==` as `= X.Y.Z - YYYY-MM-DD =` sections.
- Past releases were hand commits named `Build vX.Y.Z` containing compiled CSS/JS, `.pot`,
  `{VERSION}` replacements and re-optimized images.
- `gulp img` re-compresses committed images on every build (mozjpeg is lossy).
  `assets/wporg/icon-128x128.jpg` changed in the 1.17.0 build commit with no source edit.
- The zip uses a denylist (`**` minus exclusions), so stray files in a working copy can ship.

## Decisions

| Topic | Decision |
|---|---|
| Version source | Workflow / CLI input; blank means bump patch of current `package.json` version |
| Changelog | Extract the matching `readme.txt` section into the PR body; empty if missing; never fail |
| Where logic lives | Shared Node script in the repo; the workflow is thin glue |
| Build fixes | Drop `img` from `build`; pin Node via `.nvmrc`; switch zip to a top-level allowlist |

## Components

### `bin/release.js` (new)

Node script with no dependencies. `bin/` is already excluded from the zip.

- Usage: `node bin/release.js [X.Y.Z]` (wired as `npm run release -- [X.Y.Z]`).
- Reads the current version from `package.json`.
- If an argument is given, it must match `^\d+\.\d+\.\d+$`; otherwise exit 1 with a message.
- If no argument (or an empty string) is given, the target is the current version with patch + 1.
- If the target is numerically lower than the current version, exit 1. Equal is allowed (re-runs).
- Runs `npm version <target> --no-git-tag-version --allow-same-version`, which updates both
  `package.json` and `package-lock.json`.
- Prints `Release version: <target>`.
- Runs `npm run build` with inherited stdio and exits with its exit code.

A wrapper script is required because npm appends `-- args` to the end of the script string, so
`"release": "set-version && npm run build"` would pass the version to `build`.

### `package.json`

- Add script `"release": "node bin/release.js"`.

### `gulpfile.js`

- New task `replace_readme_ver`: in `readme.txt`, replace `/^(Stable tag:\s*).*$/m` with
  `$1<version>`, preserving the original spacing.
- `replace_ver` becomes `series(replace_plugin_file_ver, replace_readme_ver, replace_since_ver)`.
- `build` becomes `series(parallel(css, js), replace_ver, pot, composer, zip)`. The `img` task stays
  available as `npm run gulp img` for manual use.
- `plugin.files` (the zip globs) becomes a top-level allowlist:
  `assets/**`, `lib/**`, `src/**`, `vendor/**`, `autoload.php`, `readme.txt`, `wp-mail-logging.php`.
  It keeps the existing exclusions: `assets/**/*.scss`, `assets/wporg/**`, `**/tests/**`, and the
  per-type excludes (`*.map`, `*.md`, `*.sh`, `*.rst`, `*.xml`, `*.yml`, `*.dist`, `*.json`,
  `*.lock`, `AUTHORS`). The resulting file list must be identical to the current one.

### `.nvmrc` (new)

- Contents: `24`.

### `README.md`

- Add a short "Releasing" section covering `npm run release -- [version]` and the Create Release
  workflow, including the dry-run option and the repo setting prerequisite.

### `.github/workflows/create_release.yml` (new)

- `name: Create Release`
- `on: workflow_dispatch` with inputs:
  - `version`: string, not required. Description: "Release version (e.g. 1.18.0). Blank = bump patch."
  - `dry_run`: boolean, default `false`. Description: "Build and upload the zip only; no branch or PR."
- `permissions: contents: write, pull-requests: write`
- `concurrency: { group: create-release, cancel-in-progress: false }`
- `runs-on: ubuntu-24.04`

Steps:

1. **Guard branch**: if `dry_run` is false and `github.ref != refs/heads/master`, fail.
2. **Checkout** with `actions/checkout@v4`.
3. **Setup Node** with `actions/setup-node@v4`, `node-version-file: .nvmrc`, `cache: npm`.
4. **Setup PHP** with `shivammathur/setup-php@v2`: `php-version: '7.4'`,
   `extensions: mbstring, zip`, `tools: composer:v2, wp-cli`.
5. **Install Node deps**: `npm ci`. If the imagemin binary postinstall fails on the runner,
   switch to `npm ci --ignore-scripts` (safe: `build` no longer runs `img`, and `sass` is pure JS).
6. **Build**: `npm run release -- "$VERSION_INPUT"`, with `VERSION_INPUT: ${{ inputs.version }}`
   passed via `env`, never interpolated into the script.
7. **Resolve version**: read `package.json#version` into a step output `version`.
8. **Guard tag**: fail if `refs/tags/v<version>` or `refs/tags/<version>` exists on `origin`
   (older tags lack the `v` prefix).
9. **Close existing release branch/PR** (skipped on dry run): if `release/v<version>` exists on
   `origin`, close its PR with `--delete-branch`, or delete the branch if there is no PR.
10. **Commit and push** (skipped on dry run): configure `github-actions[bot]` identity,
    `git add -A`, commit `Build v<version>` unless nothing is staged, then push `HEAD` to
    `release/v<version>`.
11. **Extract changelog** (skipped on dry run): with awk, print from the line beginning with the
    literal `= <version> ` up to, but excluding, the next line beginning with `= `. Write it to a
    file. The match is literal (no regex on the version). The file may be empty.
12. **Create PR** (skipped on dry run): `gh pr create -B master -H release/v<version>`, titled
    `Release WP Mail Logging v<version>`, with `--body-file` containing:
    - Checklist:
      - `readme.txt` changelog entry present with the correct date
      - "Tested up to" checked
      - Diff reviewed
      - Zip smoke-tested on a test site
    - The extracted changelog.
    - A link to the workflow run (artifact download).
13. **Upload artifact** with `actions/upload-artifact@v4`, named `wp-mail-logging-<version>`, path
    `build/wp-mail-logging-<version>.zip`, `retention-days: 21`. If the action version in use
    supports single-file non-archived upload, use it so the download is the plugin zip itself.
14. **Step summary**: version, PR link (or "dry run"), artifact name.
15. **Slack** (skipped on dry run, and skipped when the `SLACK_RELEASE_WEBHOOK_URL` secret is empty):
    post version, PR link and run link.

## Error handling

- Invalid version input → `bin/release.js` exits 1 before touching any file.
- Version lower than current → exit 1.
- Build failure (gulp, composer, wp-cli) → non-zero exit propagates; the run stops before any push.
- Workflow run from a non-`master` ref without `dry_run` → fails at step 1.
- Version already tagged → fails at step 8, before any push.
- No changes after the build (version already final, no placeholders) → no commit; `HEAD` is still
  pushed as the release branch.
- Missing changelog section → PR body has an empty changelog; the checklist covers it.

## Testing

1. **Local release**: on a throwaway branch, run `npm run release -- 1.17.1`. Verify:
   - The diff updates `package.json` and `package-lock.json`, the plugin header, `Stable tag`,
     `.pot`, and the minified assets.
   - `build/wp-mail-logging-1.17.1.zip` exists.
   - Then reset the branch.
2. **Input validation**: `npm run release -- 1.2`, `npm run release -- 1.0.0` (lower), and
   `npm run release` (patch bump) behave as specified.
3. **Zip parity**: on the same working tree, run only `npm run gulp zip` with the old globs, then
   with the new allowlist. The `unzip -l` file lists must be identical.
4. **Workflow lint**: run `actionlint` on `create_release.yml`.
5. **Post-merge**: `workflow_dispatch` only works for workflows on the default branch, so the first
   live test runs after merge:
   - First, run with `dry_run: true` from `master` and download the artifact.
   - Then do a real run for the next release.

## Prerequisites

- Repo setting: **Settings → Actions → General → Workflow permissions → "Allow GitHub Actions to
  create and approve pull requests"** must be enabled, or step 12 fails. Requires repo admin.
- Optional: `SLACK_RELEASE_WEBHOOK_URL` repo secret.

## Out of scope

- Tagging and creating the GitHub Release.
- WordPress.org SVN deploy.
- RC builds.
- Running PHPUnit / phpcs in CI.
- Automated image optimization.
