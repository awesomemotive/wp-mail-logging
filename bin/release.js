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

    if (result.error) {
        console.error('release: could not run npm: ' + result.error.message);
        return 1;
    }

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
