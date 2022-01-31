module.exports = function (grunt) {
    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        shell: {
            composer: {
                command: './composer.phar install --no-dev --no-scripts --prefer-dist'
            },
            git_checkout: {
                command: 'git checkout-index -a -f --prefix=build/'
            },
            clean_build_dependencies: {
                command: 'rm -R build/lib/vendor/redux-framework/*.json build/lib/vendor/redux-framework/*.lock build/lib/vendor/redux-framework/sample build/lib/vendor/redux-framework/redux-templates'
            },
            copy_redux: {
                command: 'cp -R lib/vendor/redux-framework build/lib/vendor/redux-framework'
            }
        },
        clean: {
            pre_build: [
                'vendor/',
                'build/'
            ],
            post_build: [
                'build/'
            ],
            pre_compress: [
                'build/releases'
            ]
        },
        run: {
            tool: {
                cmd: 'composer'
            }
        },
        copyto: {
            vendor: {
                files: [
                    {
                        src: ['vendor/**'], dest: 'build/',
                        expand: true
                    }
                ]
            }
        },
        search: {
            credentials: {
                files: {
                    src: ["**/credentials.json"]
                },
                options: {
                    failOnMatch: true
                }
            }
        },
        compress: {
            main: {
                options: {
                    mode: 'zip',
                    archive: 'releases/<%= pkg.name %>-<%= pkg.version %>.zip'
                },
                expand: true,
                cwd: 'build/',
                src: [
                    '**/*',
                    '!build/*'
                ]
            }
        },
        gitadd: {
            add_zip: {
                options: {
                    force: true
                },
                files: {
                    src: ['releases/<%= pkg.name %>-<%= pkg.version %>.zip']
                }
            }
        },
        gittag: {
            addtag: {
                options: {
                    tag: 'release/<%= pkg.version %>',
                    message: 'Version <%= pkg.version %>'
                }
            }
        },
        gitcommit: {
            commit: {
                options: {
                    message: 'Prepared release <%= pkg.version %>.',
                    noVerify: true,
                    noStatus: false,
                    allowEmpty: true
                },
                files: {
                    src: ['package.json', 'wp-mail-logging.php', 'composer.json', 'composer.lock']
                }
            }
        },
        gitpush: {
            push: {
                options: {
                    tags: true,
                    remote: 'origin',
                    branch: 'test-release'
                }
            }
        },
        replace: {
            core_file: {
                src: ['wp-mail-logging.php'],
                overwrite: true,
                replacements: [
                    {
                        from: /Version:\s*(.*)/,
                        to: "Version: <%= pkg.version %>"
                    }
                ]
            },
            readme: {
                src: ['readme.txt'],
                overwrite: true,
                replacements: [
                    {
                        from: /Stable tag:\s*(.*)/,
                        to: "Stable tag: <%= pkg.version %>"
                    }
                ]
            }
        },
        'github-release': {
            options: {
                repository: 'kgjerstad/wp-mail-logging', // Path to repository
                auth: grunt.file.readJSON('credentials.json'),
                release: {
                    tag_name: 'release/<%= pkg.version %>',
                    name: 'v<%= pkg.version %>',
                    body: 'Description of the release',
                    draft: true,
                    prerelease: true
                }
            },
            files: {
                src: ['releases/<%= pkg.name %>-<%= pkg.version %>.zip']
            }
        },
        less: {
            compile: {
                options: {
                    paths: ['css']
                },
                files: {
                    'css/modal.css': 'css/modal.less'
                }
            }
        },
        makepot: {
            target: {
                options: {
                    domainPath: '/languages/',    // Where to save the POT file.
                    exclude: ['build/.*,lib/.*'],
                    mainFile: 'wp-mail-logging.php',    // Main project file.
                    potFilename: 'wp-mail-logging.pot', // Name of the POT file.
                    type: 'wp-plugin', // or `wp-theme`
                    updateTimestamp: true,    // Whether the POT-Creation-Date should be updated without other changes.
                    processPot: function (pot, options) {
                        pot.headers['language'] = 'en_GB';
                        var translation, // Exclude meta data from pot.
                            excluded_meta = [
                                'Plugin Name of the plugin/theme',
                                'Plugin URI of the plugin/theme',
                                'Author of the plugin/theme',
                                'Author URI of the plugin/theme'
                            ];
                        for (translation in pot.translations['']) {
                            if ('undefined' !== typeof pot.translations[''][translation].comments.extracted) {
                                if (excluded_meta.indexOf(pot.translations[''][translation].comments.extracted) >= 0) {
                                    console.log('Excluded meta: ' + pot.translations[''][translation].comments.extracted);
                                    delete pot.translations[''][translation];
                                }
                            }
                        }
                        return pot;
                    }
                }
            }
        },
        dirs: {
            lang: 'languages',
        },
        potomo: {
            dist: {
                options: {
                    poDel: false
                },
                files: [{
                    expand: true,
                    cwd: '<%= dirs.lang %>',
                    src: ['*.po'],
                    dest: '<%= dirs.lang %>',
                    ext: '.mo',
                    nonull: true
                }]
            }
        }
    });

    //load modules
    grunt.loadNpmTasks('grunt-search');
    grunt.loadNpmTasks('grunt-copy-to');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-git');
    grunt.loadNpmTasks('grunt-text-replace');
    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-github-releaser');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-potomo');

    //release tasks
    grunt.registerTask('assert-valid-copy', ['search:credentials']);
    grunt.registerTask('copy', ['shell:git_checkout']);
    grunt.registerTask('clean_pre_build', ['clean:pre_build']);
    grunt.registerTask('version_number', ['replace:core_file', 'replace:readme']);
    grunt.registerTask('pre_vcs', ['shell:composer', 'version_number', 'copy', 'copyto:vendor', 'shell:copy_redux','shell:clean_build_dependencies', 'compress']);
    grunt.registerTask('do_git', [/*'gitadd',*/ 'gitcommit', 'gittag', 'gitpush']);

    grunt.registerTask('just_build', ['clean_pre_build', 'shell:composer', 'makepot', 'potomo', 'copy', 'copyto:vendor', 'shell:copy_redux','shell:clean_build_dependencies', 'assert-valid-copy', 'compress']);
    grunt.registerTask('release', ['clean_pre_build', 'pre_vcs', 'do_git', 'github-release', 'clean:post_build']);
    grunt.registerTask('compilecss', ['less']);
};
