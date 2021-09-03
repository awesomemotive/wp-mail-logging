<?php

/**
 * PHPUnit tests bootstrap.
 */

/**
 * The Composer-generated autoloader.
 */
echo "Executing wp-mail-logging Test Suite" . PHP_EOL;

ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once( dirname( __FILE__ ) . '/../../../vendor/autoload.php' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

echo 'Tests folder: ' . $_tests_dir . PHP_EOL;

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?";
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    $path = dirname(dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-mail-logging.php';
    echo "Plugin path: " . $path;
    require $path;
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// EOF
