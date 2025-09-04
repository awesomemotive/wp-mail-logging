/**
 * Load plugins.
 */
var gulp = require('gulp'),
    cached = require('gulp-cached'),
    clean = require('gulp-clean'),
    debug = require('gulp-debug'),
    exec = require('child_process').exec,
    packageJSON = require('./package.json'),
    rename = require('gulp-rename'),
    replace = require('gulp-replace'),
    sass = require('gulp-sass')(require('sass')),
    sourcemaps = require('gulp-sourcemaps'),
    uglify = require('gulp-uglify'),
    zip = require('gulp-zip');

var plugin = {
    name: 'WP Mail Logging',
    slug: 'wp-mail-logging',
    files: [
        '**',
        'autoload.php',
        'readme.txt',
        // Exclude all the files/dirs below. Note the double negate (when ! is used inside the exclusion) - we may actually need some things.
        '!**/*.map',
        '!assets/**/*.scss',
        'assets/**/*.css',
        'assets/**/*.min.css',
        '!assets/wporg',
        '!assets/wporg/**',
        '!bin/**',
        '!**/node_modules/**',
        '!**/node_modules',
        '!**/tests',
        '!**/tests/**',
        '!**/build/**',
        '!**/build',
        '!**/*.md',
        '!**/*.sh',
        '!**/*.rst',
        '!**/*.xml',
        '!**/*.yml',
        '!**/*.dist',
        '!**/*.json',
        '!**/*.lock',
        '!**/gulpfile.js',
        '!**/AUTHORS'
    ],
    scss: [
        'assets/css/**/*.scss'
    ],
    js: [
        'assets/js/*.js',
        '!assets/js/*.min.js'
    ],
    images: [
        'assets/images/**/*',
        'assets/wporg/**/*'
    ],
    files_replace_ver: [
        "**/*.php",
        "**/*.js",
        "!**/*.min.js",
        "!gulpfile.js",
        "!lib/**",
        "!build/**",
        "!node_modules/**",
        "!vendor/**"
    ]
};

/**
 * Compile SCSS to CSS, compress.
 */
gulp.task('css', function () {
    return gulp.src(plugin.scss)
        // UnMinified file.
        .pipe(cached('processCSS'))
        .pipe(sourcemaps.init())
        .pipe(sass({outputStyle: 'expanded'}).on('error', sass.logError))
        .pipe(rename(function (path) {
            path.dirname = '/assets/css';
            path.extname = '.css';
        }))
        .pipe(sourcemaps.write())
        .pipe(gulp.dest('./'))
        // Minified file.
        .pipe(sass({outputStyle: 'compressed'}).on('error', sass.logError))
        .pipe(rename(function (path) {
            path.dirname = '/assets/css';
            path.extname = '.min.css';
        }))
        .pipe(gulp.dest('./'))
        .pipe(debug({title: '[css]'}));
});

/**
 * Compress js.
 */
gulp.task('js', function () {
    return gulp.src(plugin.js)
        .pipe(cached('processJS'))
        .pipe(uglify()).on('error', console.log)
        .pipe(rename(function (path) {
            path.dirname = '/assets/js';
            path.basename += '.min';
        }))
        .pipe(gulp.dest('.'))
        .pipe(debug({title: '[js]'}));
});

/**
 * Optimize image files.
 */
gulp.task('img', function () {
    return import('gulp-imagemin').then(function (mod) {
        var imagemin = mod && mod.default ? mod.default : mod;

        return new Promise(function (resolve, reject) {
            gulp.src(plugin.images)
                .pipe(imagemin())
                .pipe(gulp.dest(function (file) {
                    return file.base;
                }))
                .pipe(debug({title: '[img]'}))
                .on('end', resolve)
                .on('error', reject);
        });
    });
});

/**
 * Generate .pot files.
 */
gulp.task('pot', function (cb) {
    exec(
        'wp i18n make-pot ./ ./assets/languages/wp-mail-logging.pot --slug="wp-mail-logging" --domain="wp-mail-logging" --package-name="WP Mail Logging" --file-comment="" --exclude="build,node_modules,vendor"',
        function (err, stdout, stderr) {
            console.log(stdout);
            console.log(stderr);
            cb(err);
        }
    );
});

/**
 * Replace plugin version with one from package.json in the main plugin file.
 */
gulp.task('replace_plugin_file_ver', function () {
    return gulp.src(['wp-mail-logging.php'])
        .pipe(
            // File header.
            replace(
                /Version:\s*(.*)/,
                'Version: ' + packageJSON.version
            )
        )
        .pipe(gulp.dest('./'));
});

/**
 * Replace plugin version with one from package.json in @since comments in plugin PHP and JS files.
 */
gulp.task('replace_since_ver', function () {
    return gulp.src(plugin.files_replace_ver)
        .pipe(
            replace(
                /@since {VERSION}/g,
                '@since ' + packageJSON.version
            )
        )
        .pipe(
            replace(
                /@deprecated {VERSION}/g,
                '@deprecated ' + packageJSON.version
            )
        )
        .pipe(gulp.dest('./'));
});

gulp.task('replace_ver', gulp.series('replace_plugin_file_ver', 'replace_since_ver'));

/**
 * Install composer dependencies.
 */
gulp.task('composer', function (cb) {
    exec('composer build', function (err, stdout, stderr) {
        console.log(stdout);
        console.log(stderr);
        cb(err);
    });
});

gulp.task('composer:delete_vendor', function () {
    return gulp.src(['vendor'], {allowEmpty: true, read: false})
        .pipe(clean());
});

/**
 * Generate a .zip file.
 */
gulp.task('zip', function () {
    // Modifying 'base' to include plugin directory in a zip.
    return gulp.src(plugin.files, {base: '.'})
        .pipe(rename(function (file) {
            file.dirname = plugin.slug + '/' + file.dirname;
        }))
        .pipe(zip(plugin.slug + '-' + packageJSON.version + '.zip'))
        .pipe(gulp.dest('./build'))
        .pipe(debug({title: '[zip]'}));
});

gulp.task('build', gulp.series(gulp.parallel('css', 'js', 'img'), 'replace_ver', 'pot', 'composer', 'zip'));
