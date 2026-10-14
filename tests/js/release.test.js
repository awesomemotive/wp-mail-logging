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

function runRelease(dir, args, env = process.env) {
    return spawnSync(process.execPath, [SCRIPT, ...args], { cwd: dir, encoding: 'utf8', env });
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

test('CLI says why when npm cannot be started', (t) => {
    const dir = makeFixture(t);
    const before = fs.readFileSync(path.join(dir, 'package.json'), 'utf8');
    // An empty PATH means `npm` cannot be found, the same failure Windows hits without npm.cmd.
    const result = runRelease(dir, ['1.18.0'], { ...process.env, PATH: '' });

    assert.equal(result.status, 1);
    assert.match(result.stderr, /release: could not run npm: .*ENOENT/);
    assert.equal(fs.readFileSync(path.join(dir, 'package.json'), 'utf8'), before);
});

test('CLI exits with the build exit code when the build fails', (t) => {
    const dir = makeFixture(t, { build: 'node -e "process.exit(3)"' });
    const result = runRelease(dir, ['1.18.0']);

    assert.equal(result.status, 3);
});
