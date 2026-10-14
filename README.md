# :envelope: wp-mail-logging [![Build Status](https://travis-ci.org/kgjerstad/wp-mail-logging.png?branch=master)](https://travis-ci.org/kgjerstad/wp-mail-logging) [![codecov](https://codecov.io/gh/kgjerstad/wp-mail-logging/branch/master/graph/badge.svg)](https://codecov.io/gh/kgjerstad/wp-mail-logging) [![Join the chat at https://gitter.im/kgjerstad/wp-mail-logging](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/kgjerstad/wp-mail-logging?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
## Welcome ##
wp-mail-logging is a WordPress plugin that logs each mail sent by WordPress.

## Installation ##

Install the plugin from the official [Wordpress Plugin Repository](https://wordpress.org/plugins/wp-mail-logging/).

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
If you find an issue, let us know in the [Tracker](https://github.com/kgjerstad/wp-mail-logging/issues?state=open)!

## Contributions ##
Anyone is welcome to contribute to wp-mail-logging. Please create an pull request for each change you want to contribute.

There are various ways you can contribute:

### As Developer ###
1. You can clone the git repository: 'https://github.com/kgjerstad/wp-mail-logging.git'
2. Or download it directly as a ZIP file: 'https://github.com/kgjerstad/wp-mail-logging/archive/master.zip'

This will download the latest developer copy of wp-mail-logging.

Please read [Contributing](https://github.com/kgjerstad/wp-mail-logging/blob/master/CONTRIBUTING.md) if you plan to contribute.

### As Assistant ###
1. Raise an [Issue](https://github.com/kgjerstad/wp-mail-logging/issues?state=open)
3. Translate wp-mail-logging into different languages
4. Provide feedback and suggestions on [enhancements](https://github.com/kgjerstad/wp-mail-logging/issues?direction=desc&labels=Enhancement%2Cenhancement&page=1&sort=created&state=open)
