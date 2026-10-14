# Release Command and Create Release Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn "edit package.json, run `npm run build`" into one command (`npm run release -- [version]`) and a one-click GitHub Actions workflow that builds the zip, opens a `Build vX.Y.Z` release PR to `master`, and uploads the zip.

**Architecture:** `bin/release.js` resolves the version (input or patch bump), writes it to `package.json` and `package-lock.json` with `npm version`, then runs the existing gulp `build`. Gulp copies the version from `package.json` into the plugin header, the readme Stable tag and the `{VERSION}` placeholders. The workflow is thin glue: it resolves and checks the version, installs the toolchain, runs `npm run release`, then commits, opens the PR and uploads the zip.

**Tech Stack:** Node 24 (`node:test`, no new npm deps), gulp 4, Composer v2, WP-CLI, GitHub Actions (`actions/checkout@v7`, `actions/setup-node@v7`, `shivammathur/setup-php@v2`, `actions/upload-artifact@v7`, `slackapi/slack-github-action@v4`).

**Spec:** `docs/superpowers/specs/2026-10-14-release-workflow-design.md`

## Global Constraints

- Work on branch `improve-build-process`. Stage files by explicit path only. **Never stage `.gitignore` (it has unrelated local edits) or `CLAUDE.md` (untracked, local only).**
- Every commit message ends with a blank line, then `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Version format is `X.Y.Z` only: `^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$`. Blank input bumps the patch. A version lower than the current one is an error. An equal version is allowed (re-runs).
- Release branch is `release/vX.Y.Z`, the commit is `Build vX.Y.Z`, the PR title is `Release WP Mail Logging vX.Y.Z`, and the PR base is `master`.
- CI toolchain: `ubuntu-24.04`, Node from `.nvmrc` (`24`), PHP `7.4`, Composer v2, WP-CLI.
- The zip's file list must not change. Only directory entries (the root and `docs/`) may disappear.
- Indentation: JS uses 4 spaces (`.editorconfig`, `gulpfile.js`); YAML uses 2 spaces (`.travis.yml`).
- Never hand-edit the `Version:` line in `wp-mail-logging.php`. Gulp writes it.
- Write no `@since {VERSION}` / `@deprecated {VERSION}` literals in JS files. `replace_since_ver` scans `**/*.js`, including `bin/` and `tests/`.

### Deliberate deviations from the spec

The spec allows these choices or implies them:

1. **The workflow resolves and tag-checks the version before `npm ci` and the build.** The spec puts those checks after the build. They are the same checks, and a typo or a re-release now fails in seconds instead of after a full build.
2. **`npm ci --ignore-scripts` is used from the start, not as a fallback.** The workflow can't run before merge, and no remaining build step needs install scripts.
3. **The version regex rejects leading zeros (semver).** Otherwise `npm version` would reject them after validation had already passed.
4. **Surrounding whitespace in the version input is trimmed** (typing artifact in the Actions form).
5. **The artifact is named after the file (`wp-mail-logging-X.Y.Z.zip`).** `archive: false` ignores `name`, which the spec allowed.
6. **A build that changes no files fails the run.** The spec says to skip the commit and push `HEAD` anyway, but on our single-branch flow `HEAD` is `master`, so `gh pr create` would fail with "No commits between". In practice the `.pot` rewrite makes every build produce changes. The commit is also made before any existing release PR is closed, so a failed build leaves the old PR alone.

## Review Focus

1. **Someone types `v1.18.0` or ` 1.18.0 ` into the Actions form.** Spaces are trimmed. `v1.18.0` is rejected with a message showing `Expected X.Y.Z`, before any install or build. Owned by Task 1 (unit and CLI tests) and Task 4 (Step 6).
2. **Multi-digit parts: 1.9.0 → 1.10.0.** The comparison must be numeric. A text comparison would reject a valid bump and accept a downgrade. Owned by Task 1.
3. **No changelog entry for the version, or a version that is a prefix of another (`1.1` vs `= 1.10.5`).** The PR changelog should be empty, never the wrong section, and extraction stops at the next `=` line. Owned by Task 4 (Step 7).
4. **The version is already tagged, in any of the repo's three tag styles (`v1.16.0`, `1.13.1`, `release/1.9.9`).** The run must fail before building or pushing. Owned by Task 4 (Step 7).
5. **A release run changes only what it should.** The Stable tag edit touches exactly one line of `readme.txt`, no images are re-compressed, and nothing under `vendor/`, `build/` or `node_modules/` gets staged. Owned by Task 2 (Step 4) and Task 4 (Step 6).

---

### Task 1: Release command (`bin/release.js`)

**Files:**
- Create: `bin/release.js`
- Create: `tests/js/release.test.js`
- Modify: `package.json` (the `scripts` block)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `resolveVersion(current: string, input?: string): string`. Returns the release version and throws `Error` with a user-facing message on bad input. Task 4's workflow calls it via `node -e`.
  - `compareVersions(a: string, b: string): number`. Returns negative, zero or positive.
  - CLI: run from the package root as `node bin/release.js [X.Y.Z]`. It prints `Release version: X.Y.Z`, then runs `npm version X.Y.Z --no-git-tag-version --allow-same-version` and `npm run build`. It exits 1 on bad input, otherwise with the first failing npm command's exit code.
  - npm scripts: `release` (`node bin/release.js`) and `test:js`.

- [ ] **Step 1: Write the failing tests**

Create `tests/js/release.test.js`:

```js
/**
 * Tests for bin/release.js.
 *
 * Run: npm run test:js
 */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { test } = require('node:test');

const { compareVersions, resolveVersion } = require('../../bin/release.js');

const SCRIPT = path.resolve(__dirname, '../../bin/release.js');

// The fixture's build records the package.json version it saw, proving the build ran after the bump.
const RECORDING_BUILD = "node -e \"require('fs').writeFileSync('built.txt', require('./package.json').version)\"";

/**
 * Create a throwaway package (removed when the test ends) with a stale lock version, like the repo's.
 */
function makeFixture(t, { version = '1.17.0', build = RECORDING_BUILD } = {}) {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'wpml-release-'));
    t.after(() => fs.rmSync(dir, { recursive: true, force: true }));

    writeJSON(dir, 'package.json', { name: 'fixture', version, scripts: { build } });
    writeJSON(dir, 'package-lock.json', {
        name: 'fixture',
        version: '1.14.0',
        lockfileVersion: 3,
        requires: true,
        packages: { '': { name: 'fixture', version: '1.14.0' } }
    });

    return dir;
}

function writeJSON(dir, file, data) {
    fs.writeFileSync(path.join(dir, file), JSON.stringify(data, null, 2) + '\n');
}

function readJSON(dir, file) {
    return JSON.parse(fs.readFileSync(path.join(dir, file), 'utf8'));
}

function runRelease(dir, args) {
    return spawnSync(process.execPath, [SCRIPT, ...args], { cwd: dir, encoding: 'utf8' });
}

test('resolveVersion bumps the patch when no version is given', () => {
    assert.equal(resolveVersion('1.17.0', undefined), '1.17.1');
    assert.equal(resolveVersion('1.17.0', ''), '1.17.1');
    assert.equal(resolveVersion('1.17.9', '   '), '1.17.10');
});

test('resolveVersion accepts a higher or equal version', () => {
    assert.equal(resolveVersion('1.17.0', '1.18.0'), '1.18.0');
    assert.equal(resolveVersion('1.17.0', '2.0.0'), '2.0.0');
    assert.equal(resolveVersion('1.17.0', '1.17.0'), '1.17.0');
});

test('resolveVersion trims whitespace around the version', () => {
    assert.equal(resolveVersion('1.17.0', ' 1.18.0\n'), '1.18.0');
});

test('resolveVersion rejects malformed versions and shows the expected format', () => {
    for (const bad of ['1.18', 'v1.18.0', '1.18.0-rc.1', '1.18.0.1', '01.18.0', 'latest']) {
        assert.throws(() => resolveVersion('1.17.0', bad), /Expected X\.Y\.Z/, bad);
    }
});

test('resolveVersion rejects versions lower than the current one', () => {
    assert.throws(() => resolveVersion('1.17.0', '1.16.9'), /lower than the current version 1\.17\.0/);
});

test('versions compare each part as a number, not as text', () => {
    assert.ok(compareVersions('1.10.0', '1.9.0') > 0);
    assert.ok(compareVersions('1.9.0', '1.10.0') < 0);
    assert.equal(compareVersions('1.17.0', '1.17.0'), 0);
    assert.equal(resolveVersion('1.9.0', '1.10.0'), '1.10.0');
    assert.throws(() => resolveVersion('1.10.0', '1.9.0'), /lower/);
});

test('CLI writes the version to package.json and package-lock.json, then builds', (t) => {
    const dir = makeFixture(t);
    const result = runRelease(dir, ['1.18.0']);

    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /Release version: 1\.18\.0/);
    assert.equal(readJSON(dir, 'package.json').version, '1.18.0');
    assert.equal(readJSON(dir, 'package-lock.json').version, '1.18.0');
    assert.equal(readJSON(dir, 'package-lock.json').packages[''].version, '1.18.0');
    assert.equal(fs.readFileSync(path.join(dir, 'built.txt'), 'utf8'), '1.18.0');
});

test('CLI with no version, or an empty one, bumps the patch', (t) => {
    for (const args of [[], ['']]) {
        const dir = makeFixture(t);
        const result = runRelease(dir, args);

        assert.equal(result.status, 0, result.stderr);
        assert.equal(readJSON(dir, 'package.json').version, '1.17.1');
    }
});

test('CLI rejects a malformed version without touching files or building', (t) => {
    const dir = makeFixture(t);
    const before = fs.readFileSync(path.join(dir, 'package.json'), 'utf8');
    const result = runRelease(dir, ['v1.18.0']);

    assert.equal(result.status, 1);
    assert.match(result.stderr, /Invalid version "v1\.18\.0"/);
    assert.equal(fs.readFileSync(path.join(dir, 'package.json'), 'utf8'), before);
    assert.equal(fs.existsSync(path.join(dir, 'built.txt')), false);
});

test('CLI rejects a lower version without touching files or building', (t) => {
    const dir = makeFixture(t);
    const before = fs.readFileSync(path.join(dir, 'package.json'), 'utf8');
    const result = runRelease(dir, ['1.16.0']);

    assert.equal(result.status, 1);
    assert.match(result.stderr, /lower than the current version/);
    assert.equal(fs.readFileSync(path.join(dir, 'package.json'), 'utf8'), before);
    assert.equal(fs.existsSync(path.join(dir, 'built.txt')), false);
});

test('CLI exits with the build exit code when the build fails', (t) => {
    const dir = makeFixture(t, { build: 'node -e "process.exit(3)"' });
    const result = runRelease(dir, ['1.18.0']);

    assert.equal(result.status, 3);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `node --test 'tests/js/*.test.js'`
Expected: FAIL with `Cannot find module '../../bin/release.js'`.

- [ ] **Step 3: Write the implementation**

Create `bin/release.js`:

```js
/**
 * Set the plugin release version, then build the release zip.
 *
 * Usage: npm run release -- [X.Y.Z]
 *
 * With no version (or a blank one), bumps the patch of the current package.json version.
 * Writes the version to package.json and package-lock.json via `npm version`, then runs
 * `npm run build`, whose replace_ver task copies it into the plugin header, the readme.txt
 * Stable tag and the version placeholders in docblocks.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

// X.Y.Z with no leading zeros (semver), so `npm version` never rejects a version we accepted.
const VERSION_PATTERN = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;

/**
 * Split an X.Y.Z version into numbers.
 *
 * @param {string} version Version string.
 * @return {number[]} [major, minor, patch].
 */
function parseVersion(version) {
    const match = VERSION_PATTERN.exec(version);

    if (!match) {
        throw new Error('Invalid version "' + version + '". Expected X.Y.Z, e.g. 1.18.0.');
    }

    return match.slice(1).map(Number);
}

/**
 * Compare two X.Y.Z versions part by part, as numbers.
 *
 * @param {string} a First version.
 * @param {string} b Second version.
 * @return {number} Negative if a < b, 0 if equal, positive if a > b.
 */
function compareVersions(a, b) {
    const partsA = parseVersion(a);
    const partsB = parseVersion(b);

    for (let i = 0; i < 3; i++) {
        if (partsA[i] !== partsB[i]) {
            return partsA[i] - partsB[i];
        }
    }

    return 0;
}

/**
 * Work out the release version.
 *
 * @param {string} current Current package.json version.
 * @param {string} [input]  Requested version; blank means bump the patch.
 * @return {string} Release version.
 */
function resolveVersion(current, input) {
    const requested = (input || '').trim();

    if (requested === '') {
        const [major, minor, patch] = parseVersion(current);

        return major + '.' + minor + '.' + (patch + 1);
    }

    if (compareVersions(requested, current) < 0) {
        throw new Error('Version ' + requested + ' is lower than the current version ' + current + '.');
    }

    return requested;
}

/**
 * Run an npm command in the current directory, streaming its output.
 *
 * @param {string[]} args npm arguments.
 * @return {number} Exit code (1 if npm could not start or was killed).
 */
function npm(args) {
    const result = spawnSync('npm', args, { stdio: 'inherit' });

    return result.status === null ? 1 : result.status;
}

/**
 * CLI entry point.
 *
 * @param {string[]} argv Arguments after the script name.
 * @return {number} Exit code.
 */
function main(argv) {
    const pkg = JSON.parse(fs.readFileSync(path.join(process.cwd(), 'package.json'), 'utf8'));
    let version;

    try {
        version = resolveVersion(pkg.version, argv[0]);
    } catch (error) {
        console.error('release: ' + error.message);
        return 1;
    }

    console.log('Release version: ' + version);

    const status = npm(['version', version, '--no-git-tag-version', '--allow-same-version']);

    return status !== 0 ? status : npm(['run', 'build']);
}

if (require.main === module) {
    process.exitCode = main(process.argv.slice(2));
}

module.exports = { compareVersions, resolveVersion };
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `node --test 'tests/js/*.test.js'`
Expected: `ℹ pass 11`, `ℹ fail 0`.

- [ ] **Step 5: Add the npm scripts**

In `package.json`, replace:

```json
  "scripts": {
    "gulp": "cross-env node ./node_modules/.bin/gulp",
    "build": "npm run gulp build"
  },
```

with:

```json
  "scripts": {
    "gulp": "cross-env node ./node_modules/.bin/gulp",
    "build": "npm run gulp build",
    "release": "node bin/release.js",
    "test:js": "node --test \"tests/js/*.test.js\""
  },
```

Run: `npm run test:js`
Expected: `ℹ pass 11`, `ℹ fail 0`. Then run `git status --short package.json package-lock.json`. Expected: only ` M package.json`; the repo's own version is untouched.

- [ ] **Step 6: Commit**

```bash
git add bin/release.js tests/js/release.test.js package.json
git commit -m "feat: add npm run release to set the version and build the zip

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Copy the version into the readme.txt Stable tag

**Files:**
- Modify: `gulpfile.js`: add a `replace_readme_ver` task after `replace_plugin_file_ver`, and extend `replace_ver`.

**Interfaces:**
- Consumes: `package.json#version`, read at gulpfile load as `packageJSON.version`.
- Produces: gulp task `replace_readme_ver`. `replace_ver` = `series('replace_plugin_file_ver', 'replace_readme_ver', 'replace_since_ver')`. `build` already runs `replace_ver`, so `npm run release` (Task 1) picks this up automatically.

- [ ] **Step 1: Run the task to verify it doesn't exist yet**

Run: `npm run gulp replace_readme_ver`
Expected: FAIL with `Task never defined: replace_readme_ver`.

- [ ] **Step 2: Add the task**

In `gulpfile.js`, replace:

```js
gulp.task('replace_ver', gulp.series('replace_plugin_file_ver', 'replace_since_ver'));
```

with:

```js
/**
 * Replace the Stable tag in readme.txt with the version from package.json.
 */
gulp.task('replace_readme_ver', function () {
    return gulp.src(['readme.txt'])
        .pipe(
            // Header line only (no g flag); [ \t]* keeps the original spacing and never crosses a newline.
            replace(
                /^(Stable tag:[ \t]*).*$/m,
                '$1' + packageJSON.version
            )
        )
        .pipe(gulp.dest('./'));
});

gulp.task('replace_ver', gulp.series('replace_plugin_file_ver', 'replace_readme_ver', 'replace_since_ver'));
```

- [ ] **Step 3: Run the task against a temporary version**

Run:

```bash
npm version 1.17.9 --no-git-tag-version
npm run gulp replace_readme_ver
git diff --unified=0 readme.txt
```

Expected: the diff shows exactly one changed line:

```
-Stable tag: 1.17.0
+Stable tag: 1.17.9
```

- [ ] **Step 4: Run the whole `replace_ver` chain and check its reach**

Run:

```bash
npm run gulp replace_ver
git diff --stat -- . ':!gulpfile.js' ':!.gitignore'
grep -n '^ \* Version:\|^Stable tag:' wp-mail-logging.php readme.txt
```

Expected:
- `git diff --stat` lists only `package.json`, `package-lock.json`, `readme.txt` and `wp-mail-logging.php`, and `readme.txt` shows `1 +-`. The pathspec leaves out this task's own uncommitted `gulpfile.js` and the pre-existing `.gitignore` edits.
- The grep shows `Version: 1.17.9` and `Stable tag: 1.17.9`.

- [ ] **Step 5: Restore the temporary version**

Run: `git checkout -- package.json package-lock.json readme.txt wp-mail-logging.php`
Expected: `git status --short` shows only `M gulpfile.js` plus the pre-existing ` M .gitignore` and `?? CLAUDE.md`.

- [ ] **Step 6: Commit**

```bash
git add gulpfile.js
git commit -m "feat: copy the release version into the readme.txt Stable tag

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Release build contents (no image re-compression, zip allowlist)

**Files:**
- Modify: `gulpfile.js`: the `plugin.files` array (the zip globs) and the `build` task line.

**Interfaces:**
- Consumes: Task 2's `replace_ver`.
- Produces: `build` = `series(parallel('css', 'js'), 'replace_ver', 'pot', 'composer', 'zip')`. The `img` task still exists for manual runs (`npm run gulp img`). The zip lands at `build/wp-mail-logging-<version>.zip`, which Task 4 uploads.

- [ ] **Step 1: Capture the current zip file list (baseline)**

Run:

```bash
npm run gulp zip
unzip -Z1 build/wp-mail-logging-1.17.0.zip | sort > build/zip-files-before.txt
wc -l build/zip-files-before.txt
```

Expected: the zip is written to `build/`. Note the line count; `build/` is gitignored.

- [ ] **Step 2: Replace the zip globs with an allowlist**

In `gulpfile.js`, replace the whole `files: [ ... ],` array inside `var plugin = {` with the following. The old array runs from `'**',` through `'!**/AUTHORS'`.

```js
    // What ships in the zip: an allowlist of paths, then dev-only file types dropped from inside them.
    files: [
        'assets/**',
        'lib/**',
        'src/**',
        'vendor/**',
        'autoload.php',
        'readme.txt',
        'wp-mail-logging.php',
        '!assets/**/*.scss',
        '!assets/wporg',
        '!assets/wporg/**',
        '!**/tests',
        '!**/tests/**',
        '!**/*.map',
        '!**/*.md',
        '!**/*.sh',
        '!**/*.rst',
        '!**/*.xml',
        '!**/*.yml',
        '!**/*.dist',
        '!**/*.json',
        '!**/*.lock',
        '!**/AUTHORS'
    ],
```

- [ ] **Step 3: Drop `img` from `build`**

In `gulpfile.js`, replace:

```js
gulp.task('build', gulp.series(gulp.parallel('css', 'js', 'img'), 'replace_ver', 'pot', 'composer', 'zip'));
```

with:

```js
// img is not part of build: it re-compresses committed images (lossy) on every run. Run it by hand: npm run gulp img.
gulp.task('build', gulp.series(gulp.parallel('css', 'js'), 'replace_ver', 'pot', 'composer', 'zip'));
```

- [ ] **Step 4: Rebuild the zip and compare file lists**

Run:

```bash
npm run gulp zip
unzip -Z1 build/wp-mail-logging-1.17.0.zip | sort > build/zip-files-after.txt
diff <(grep -v '/$' build/zip-files-before.txt) <(grep -v '/$' build/zip-files-after.txt) && echo FILES-IDENTICAL
echo '--- new entries (expect none) ---'; comm -13 build/zip-files-before.txt build/zip-files-after.txt
echo '--- dropped entries (expect only dirs: root and docs/) ---'; comm -23 build/zip-files-before.txt build/zip-files-after.txt
```

Expected:
- `FILES-IDENTICAL` is printed.
- No new entries.
- Every dropped entry ends in `/` and is either the `wp-mail-logging/` root or under `wp-mail-logging/docs/`.

If any file entry differs, stop and fix the globs. The zip contents must not change.

- [ ] **Step 5: Check the `build` task tree**

Run: `npm run gulp -- --tasks --depth 4`
Expected: under `build`, the parallel group lists `css` and `js` only, followed by `replace_ver`, `pot`, `composer` and `zip`. No `img`. `img` is still listed as a top-level task.

- [ ] **Step 6: Commit**

```bash
git add gulpfile.js
git commit -m "build: ship an allowlist zip and keep lossy img out of the release build

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Create Release workflow

**Files:**
- Create: `.nvmrc`
- Create: `.github/workflows/create_release.yml`

**Interfaces:**
- Consumes: `resolveVersion` from `bin/release.js` (Task 1); `npm run release` (Task 1); the zip at `build/wp-mail-logging-<version>.zip` (Task 3).
- Produces: a manually triggered workflow `Create Release` with inputs `version` (string) and `dry_run` (boolean); step output `steps.version.outputs.version`; branch `release/v<version>`; a PR to `master`; artifact `wp-mail-logging-<version>.zip`.

- [ ] **Step 1: Pin Node**

Create `.nvmrc` with this single line:

```
24
```

- [ ] **Step 2: Write the workflow**

Create `.github/workflows/create_release.yml`:

```yaml
# Create Release: build the plugin zip on a clean runner, commit the build output to
# release/vX.Y.Z, open a PR to master, and upload the zip.
#
# Notes:
# - The version comes from the `version` input (blank = bump patch) and is applied by
#   `npm run release` (bin/release.js), which also runs the gulp build.
# - The changelog is read from readme.txt (`= X.Y.Z - YYYY-MM-DD =` sections).
# - `dry_run` builds and uploads the zip without pushing a branch or opening a PR.
#
# Requires: Settings > Actions > General > "Allow GitHub Actions to create and approve pull requests".

name: Create Release

on:
  workflow_dispatch:
    inputs:
      version:
        description: 'Release version (e.g. 1.18.0). Blank = bump patch.'
        required: false
        type: string
        default: ''
      dry_run:
        description: 'Build and upload the zip only; no branch or PR.'
        required: false
        type: boolean
        default: false

permissions:
  contents: write
  pull-requests: write

concurrency:
  group: create-release
  cancel-in-progress: false

jobs:
  create-release:
    name: Create Release
    runs-on: ubuntu-24.04
    env:
      SLACK_RELEASE_WEBHOOK_URL: ${{ secrets.SLACK_RELEASE_WEBHOOK_URL }}

    steps:
      - name: Require master for real releases
        if: ${{ !inputs.dry_run && github.ref != 'refs/heads/master' }}
        run: |
          echo "::error::Run Create Release from master, or enable dry_run. Current ref: $GITHUB_REF"
          exit 1

      - name: Checkout
        uses: actions/checkout@v7

      - name: Setup Node
        uses: actions/setup-node@v7
        with:
          node-version-file: .nvmrc
          cache: npm

      # Resolve and check the version before installing anything, so a typo or an
      # already-released version fails in seconds.
      - name: Resolve version
        id: version
        env:
          VERSION_INPUT: ${{ inputs.version }}
        run: |
          VERSION=$(node -e 'console.log(require("./bin/release.js").resolveVersion(require("./package.json").version, process.argv[1]))' -- "$VERSION_INPUT")
          echo "Release version: $VERSION"
          echo "version=$VERSION" >> "$GITHUB_OUTPUT"

      - name: Refuse an already-tagged version
        env:
          VERSION: ${{ steps.version.outputs.version }}
        run: |
          # Tags have used three styles over the years: v1.16.0, 1.13.1 and release/1.9.9.
          if git ls-remote --exit-code --tags origin "refs/tags/v$VERSION" "refs/tags/$VERSION" "refs/tags/release/$VERSION" >/dev/null; then
            echo "::error::$VERSION is already tagged. Choose a new version."
            exit 1
          fi

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          extensions: mbstring, zip
          tools: composer:v2, wp-cli
          coverage: none

      # --ignore-scripts: no release build step (css, js, pot, composer, zip) needs install
      # scripts. Only the imagemin packages ship postinstall binaries, and build no longer runs img.
      - name: Install Node dependencies
        run: npm ci --ignore-scripts

      - name: Set version and build
        env:
          VERSION: ${{ steps.version.outputs.version }}
        run: npm run release -- "$VERSION"

      # Commit locally before touching any existing release PR, so a bad build fails
      # without closing the PR it would have replaced.
      - name: Commit release build
        if: ${{ !inputs.dry_run }}
        env:
          VERSION: ${{ steps.version.outputs.version }}
        run: |
          git config user.name 'github-actions[bot]'
          git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
          git add -A
          # A PR needs at least one commit over master. Every build rewrites the .pot
          # (POT-Creation-Date), so an empty diff means the build did not do its job.
          if git diff --cached --quiet; then
            echo "::error::The build changed no files, so there is nothing to put in a release PR."
            exit 1
          fi
          git commit -m "Build v$VERSION" --no-verify

      - name: Close existing release PR
        if: ${{ !inputs.dry_run }}
        env:
          BRANCH: release/v${{ steps.version.outputs.version }}
          GH_TOKEN: ${{ github.token }}
        run: |
          if git ls-remote --exit-code --heads origin "$BRANCH" >/dev/null; then
            pr_url=$(gh pr list --head "$BRANCH" --state open --json url --jq '.[0].url // empty')
            if [ -n "$pr_url" ]; then
              echo "Closing existing PR: $pr_url"
              gh pr close "$pr_url" --delete-branch
            else
              git push origin --delete "$BRANCH"
            fi
          fi

      - name: Push release branch
        if: ${{ !inputs.dry_run }}
        env:
          VERSION: ${{ steps.version.outputs.version }}
        run: git push origin "HEAD:refs/heads/release/v$VERSION" --no-verify

      - name: Extract changelog
        if: ${{ !inputs.dry_run }}
        env:
          VERSION: ${{ steps.version.outputs.version }}
        run: |
          awk -v v="$VERSION" '
            index($0, "= " v " ") == 1 { found = 1; print; next }
            found && /^=/ { exit }
            found { print }
          ' readme.txt > "$RUNNER_TEMP/changelog.txt"
          echo "Changelog lines for $VERSION: $(wc -l < "$RUNNER_TEMP/changelog.txt")"

      - name: Create pull request
        id: pr
        if: ${{ !inputs.dry_run }}
        env:
          VERSION: ${{ steps.version.outputs.version }}
          GH_TOKEN: ${{ github.token }}
          RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
        run: |
          {
            echo "## Release WP Mail Logging v$VERSION"
            echo
            echo "### Checklist"
            echo "- [ ] \`readme.txt\` has a \`= $VERSION - YYYY-MM-DD =\` changelog entry with the release date."
            echo "- [ ] \`readme.txt\` \"Tested up to\" is current."
            echo "- [ ] Diff reviewed: version bumps, \`.pot\`, minified assets, docblock version replacements."
            echo "- [ ] Zip from the [workflow run]($RUN_URL) installed and smoke-tested on a test site."
            echo
            echo "### Changelog"
            echo '```'
            cat "$RUNNER_TEMP/changelog.txt"
            echo '```'
          } > "$RUNNER_TEMP/pr-body.md"
          pr_url=$(gh pr create --base master --head "release/v$VERSION" \
            --title "Release WP Mail Logging v$VERSION" \
            --body-file "$RUNNER_TEMP/pr-body.md")
          echo "url=$pr_url" >> "$GITHUB_OUTPUT"

      # archive: false uploads the plugin zip as-is, so the download is the zip itself
      # (the artifact takes the file's name).
      - name: Upload zip
        uses: actions/upload-artifact@v7
        with:
          path: build/wp-mail-logging-${{ steps.version.outputs.version }}.zip
          archive: false
          retention-days: 21
          if-no-files-found: error

      - name: Summary
        env:
          VERSION: ${{ steps.version.outputs.version }}
          PR_URL: ${{ steps.pr.outputs.url }}
        run: |
          {
            echo "### WP Mail Logging v$VERSION"
            echo "- **Zip:** \`wp-mail-logging-$VERSION.zip\` (see Artifacts below)"
            if [ -n "$PR_URL" ]; then
              echo "- **PR:** $PR_URL"
            else
              echo "- **PR:** none (dry run)"
            fi
          } >> "$GITHUB_STEP_SUMMARY"

      - name: Notify Slack
        if: ${{ !inputs.dry_run && env.SLACK_RELEASE_WEBHOOK_URL != '' }}
        uses: slackapi/slack-github-action@v4
        with:
          webhook: ${{ env.SLACK_RELEASE_WEBHOOK_URL }}
          webhook-type: incoming-webhook
          payload: |
            text: "WP Mail Logging v${{ steps.version.outputs.version }} is ready: <${{ steps.pr.outputs.url }}|Release PR> | <${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}|Download zip>"
```

- [ ] **Step 3: Lint the workflow**

Run:

```bash
command -v actionlint >/dev/null || brew install actionlint shellcheck
actionlint .github/workflows/create_release.yml && echo ACTIONLINT-OK
```

Expected: `ACTIONLINT-OK` with no findings.
- If actionlint flags an input only because its bundled action metadata predates the pinned version (for example `archive` on `upload-artifact@v7`), check that input in the action's `action.yml` at that tag. Example: `gh api repos/actions/upload-artifact/contents/action.yml?ref=v7 --jq .content | base64 -d`. If the input is there, proceed.
- Fix every other finding.

- [ ] **Step 4: Commit** (the clean-clone simulation in Step 6 clones committed state)

```bash
git add .nvmrc .github/workflows/create_release.yml
git commit -m "ci: add Create Release workflow

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 5: Simulate the runner. Clean clone, install, resolve version**

Run:

```bash
SIM="${TMPDIR:-/tmp}/wpml-ci-sim"; rm -rf "$SIM"
git clone --quiet --branch improve-build-process "$PWD" "$SIM"
cd "$SIM" && npm ci --ignore-scripts
node -e 'console.log(require("./bin/release.js").resolveVersion(require("./package.json").version, process.argv[1]))' -- ""
node -e 'console.log(require("./bin/release.js").resolveVersion(require("./package.json").version, process.argv[1]))' -- "v1.18.0"; echo "exit=$?"
```

Expected:
- `npm ci` succeeds.
- The first `node -e` prints `1.17.1`.
- The second prints an `Error: Invalid version "v1.18.0". Expected X.Y.Z, e.g. 1.18.0.` stack and `exit=1`.

- [ ] **Step 6: Simulate the runner. Build and inspect what CI would commit**

Run:

```bash
SIM="${TMPDIR:-/tmp}/wpml-ci-sim"; cd "$SIM"
npm run release -- 1.17.1
ls -l build/wp-mail-logging-1.17.1.zip
grep -n '^ \* Version:\|^Stable tag:\|"version"' wp-mail-logging.php readme.txt package.json
grep -m2 '"version"' package-lock.json
git add -A && git diff --cached --name-only
git diff --cached --name-only | grep -E '^(assets/images|assets/wporg|vendor|build|node_modules)/' && echo UNEXPECTED-FILES || echo STAGED-SET-OK
```

Expected:
- The release exits 0 and the zip exists.
- The header, Stable tag, `package.json` and both lock `version` lines all say `1.17.1`.
- The staged list contains `package.json`, `package-lock.json`, `readme.txt`, `wp-mail-logging.php` and `assets/languages/wp-mail-logging.pot`. It may also contain rebuilt `assets/css/*.css` and `assets/js/*.min.js` (expected gulp output).
- The last line is `STAGED-SET-OK`.

- [ ] **Step 7: Check the workflow's tag guard and changelog extraction**

Run these commands from the repo root, not the clone. Step 6 left the shell inside the clone, so `cd` back first. Apart from the loops, the guard and awk commands are copied verbatim from the workflow.

```bash
cd path/to/wp-mail-logging   # the repo checkout, not the clone
for VERSION in 1.16.0 1.13.1 1.9.9 1.17.1; do
  if git ls-remote --exit-code --tags origin "refs/tags/v$VERSION" "refs/tags/$VERSION" "refs/tags/release/$VERSION" >/dev/null; then
    echo "$VERSION: REFUSED (tagged)"
  else
    echo "$VERSION: allowed"
  fi
done
for VERSION in 1.17.0 1.17.1 1.1; do
  echo "--- $VERSION ---"
  awk -v v="$VERSION" '
    index($0, "= " v " ") == 1 { found = 1; print; next }
    found && /^=/ { exit }
    found { print }
  ' readme.txt
done
```

Expected:
- Tag guard: `1.16.0: REFUSED (tagged)`, `1.13.1: REFUSED (tagged)` (an old tag without `v`), `1.9.9: REFUSED (tagged)` (a `release/1.9.9` tag), `1.17.1: allowed`.
- Changelog for `1.17.0`: the `= 1.17.0 - 2026-09-14 =` line, its five entries, then a blank line. Nothing from 1.16.0.
- Changelog for `1.17.1` and `1.1`: nothing.

- [ ] **Step 8: Remove the simulation clone**

Run: `rm -rf "${TMPDIR:-/tmp}/wpml-ci-sim"`

---

### Task 5: Documentation

**Files:**
- Modify: `README.md`: add a "Releasing" section before `## Bugs ##`.
- Modify (local only, **do not commit**): `CLAUDE.md`, the "Build & release" section.

**Interfaces:**
- Consumes: the commands and workflow from Tasks 1–4.
- Produces: docs only.

- [ ] **Step 1: Add the Releasing section to README.md**

In `README.md`, replace:

```markdown
## Bugs ##
```

with:

````markdown
## Releasing ##

Cut a release from GitHub: **Actions → Create Release → Run workflow** on `master`.

- **version**: e.g. `1.18.0`. Leave blank to bump the patch version.
- **dry_run**: build and upload the zip only, without a branch or PR.

The workflow:

1. Sets the version in `package.json`, `package-lock.json`, the plugin header, the `readme.txt` Stable tag and the docblock version placeholders.
2. Builds the zip.
3. Commits the result as `Build vX.Y.Z` to `release/vX.Y.Z`.
4. Opens a PR to `master` with that version's `readme.txt` changelog.
5. Attaches `wp-mail-logging-X.Y.Z.zip` to the run.

Add the `= X.Y.Z - YYYY-MM-DD =` changelog entry to `readme.txt` on `master` before running it.

The workflow needs **Settings → Actions → General → Allow GitHub Actions to create and approve pull requests** turned on.

To build locally instead:

```
npm run release -- 1.18.0   # or `npm run release` to bump the patch
```

The zip lands in `build/`. Requires Node (see `.nvmrc`), Composer and WP-CLI.

## Bugs ##
````

- [ ] **Step 2: Update the local CLAUDE.md (untracked, not committed)**

In `CLAUDE.md`, replace:

```
npm run build              # full release build: css, js, img, @version replace, .pot, composer prod install, zip
```

with:

```
npm run release -- 1.18.0  # set the version everywhere (blank = bump patch), then build the zip
npm run build              # build at the current package.json version: css, js, version replace, .pot, composer prod install, zip
npm run gulp img           # lossy image optimization; run by hand, not part of build
npm run test:js            # node:test tests for bin/release.js
```

In `CLAUDE.md`, replace:

```
`gulp build` composes: `css js img` in parallel, then `replace_ver`, `pot`, `composer`, `zip`. Output lands in `build/wp-mail-logging-<version>.zip`.
```

with:

```
`gulp build` composes: `css js` in parallel, then `replace_ver`, `pot`, `composer`, `zip`. Output lands in `build/wp-mail-logging-<version>.zip`. The zip uses an allowlist (`assets lib src vendor autoload.php readme.txt wp-mail-logging.php`).

**Releases**: `.github/workflows/create_release.yml` (Actions → Create Release) runs `npm run release` on a clean runner, commits `Build vX.Y.Z` to `release/vX.Y.Z`, opens a PR to `master`, and uploads the zip. `dry_run` skips the branch and PR.
```

In `CLAUDE.md`, replace:

```
**Version bumping**: edit `package.json#version`, then `npm run gulp replace_ver` rewrites the plugin header in `wp-mail-logging.php` and replaces every `@since {VERSION}` / `@deprecated {VERSION}` placeholder in PHP/JS files. Do not hand-edit the plugin header version.
```

with:

```
**Version bumping**: `npm run release -- X.Y.Z` (or the Create Release workflow) writes `package.json` + `package-lock.json` via `npm version`, and the build's `replace_ver` rewrites the plugin header, the `readme.txt` Stable tag, and every `@since {VERSION}` / `@deprecated {VERSION}` placeholder in PHP/JS files. Do not hand-edit the plugin header version.
```

- [ ] **Step 3: Commit README.md only**

```bash
git add README.md
git commit -m "docs: document the release command and Create Release workflow

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git status --short
```

Expected after commit: `git status --short` shows only ` M .gitignore` and `?? CLAUDE.md`.

---

### Task 6: Post-merge verification (manual; run by a maintainer after this branch merges to `master`)

GitHub only lets you trigger `workflow_dispatch` workflows that exist on the default branch, so none of this can run before merge.

**Files:** none.

- [ ] **Step 1: Enable the repo setting.** A repo admin turns on **Settings → Actions → General → Workflow permissions → "Allow GitHub Actions to create and approve pull requests"**.

- [ ] **Step 2: Dry run from master.** Run `gh workflow run create_release.yml --ref master -f dry_run=true`, then `gh run watch`.
  Expected:
  - The run succeeds.
  - The summary shows `PR: none (dry run)`.
  - The artifact `wp-mail-logging-1.17.1.zip` downloads as the plugin zip itself, not a zip inside a zip. Don't use `gh run download` for this check: it always extracts artifacts, which hides the difference. Instead, download it from the run page's Artifacts list in the browser, or fetch the raw download and list it:

    ```bash
    ID=$(gh api repos/awesomemotive/wp-mail-logging/actions/runs/<run-id>/artifacts --jq '.artifacts[0].id')
    gh api "repos/awesomemotive/wp-mail-logging/actions/artifacts/$ID/zip" > dl.zip && unzip -l dl.zip | head
    ```

    The entries should start with `wp-mail-logging/`, with no nested `.zip`.
  - `git ls-remote --heads origin 'release/*'` lists no new branch.

- [ ] **Step 3: Version typo fails fast.** Run `gh workflow run create_release.yml --ref master -f dry_run=true -f version=v1.18.0`.
  Expected: the run fails at **Resolve version** in under a minute, and the log shows `Invalid version "v1.18.0". Expected X.Y.Z`.

- [ ] **Step 4: Non-master guard.** Run `gh workflow run create_release.yml --ref <branch>` with `dry_run` left false. Use a branch cut from `master` after the merge. GitHub runs the workflow file from that ref, so on an older branch without `create_release.yml` it rejects the dispatch (HTTP 422) and the guard never runs.
  Expected: the run fails at **Require master for real releases**.

- [ ] **Step 5: First real release.** At the next real release, add the changelog entry to `readme.txt` on `master`, then run the workflow with the version.
  Expected:
  - A PR `Release WP Mail Logging vX.Y.Z` opens against `master`, with a `Build vX.Y.Z` commit, the checklist and the changelog.
  - Re-running the same version closes that PR and opens a fresh one.
