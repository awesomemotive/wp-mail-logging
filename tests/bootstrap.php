<?php

echo "Executing wp-mail-logging Test Suite" . PHP_EOL;

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

echo 'Tests folder: ' . $_tests_dir . PHP_EOL . PHP_EOL;

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../bootstrap-shortcodes.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

