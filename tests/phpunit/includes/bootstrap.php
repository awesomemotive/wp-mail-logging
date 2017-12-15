<?php

/**
 * PHPUnit tests bootstrap.
 */

/**
 * The Composer-generated autoloader.
 */
echo "Executing wp-mail-logging Test Suite" . PHP_EOL;

require_once( dirname( __FILE__ ) . '/../../../vendor/autoload.php' );

$loader = WPPPB_Loader::instance();

echo 'Tests folder: ' . $loader->locate_wp_tests_config() . PHP_EOL;
$loader->add_plugin( 'wp-mail-logging/wp-mail-logging.php' );
$loader->load_wordpress();

require_once( dirname( __FILE__ ) . '/../../../wp-mail-logging.php' );
// EOF
