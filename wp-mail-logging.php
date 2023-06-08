<?php
/**
 * Plugin Name: WP Mail Logging
 * Plugin URI: https://wordpress.org/plugins/wp-mail-logging/
 * Version: 1.11.0
 * Requires at least: 5.0
 * Requires PHP: 7.1
 * Author: WP Mail Logging Team
 * Author URI: https://github.com/awesomemotive/wp-mail-logging
 * License: GPLv3
 * Description: Logs each email sent by WordPress.
 * Text Domain: wp-mail-logging
 * Domain Path: /assets/languages
 */

namespace No3x\WPML;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPML_PHP_MIN_VERSION', '7.1' );
define( 'WP_MAIL_LOGGING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MAIL_LOGGING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check the PHP version and give a useful error message if the user's version is less than the required version
 * @return boolean true if version check passed. If false, triggers an error which WP will handle, by displaying
 * an error message on the Admin page
 */
function WPML_noticePhpVersionWrong() {
    echo '<div class="error">' .
        __( 'Error: plugin "WP Mail Logging" requires a newer version of PHP to be running.',  'wp-mail-logging' ).
        '<br/>' . __( 'Minimal version of PHP required: ', 'wp-mail-logging' ) . '<strong>' . WPML_PHP_MIN_VERSION . '</strong>' .
        '<br/>' . __( 'Your server\'s PHP version: ', 'wp-mail-logging' ) . '<strong>' . phpversion() . '</strong>' .
        '</div>';
}


function WPML_PhpVersionCheck() {
    if ( version_compare( phpversion(), WPML_PHP_MIN_VERSION ) < 0 ) {
        add_action( 'admin_notices',  __NAMESPACE__ . '\WPML_noticePhpVersionWrong' );
        return false;
    }
    return true;
}


/**
 * Initialize internationalization (i18n) for this plugin.
 * References:
 *      http://codex.wordpress.org/I18n_for_WordPress_Developers
 *      http://www.wdmac.com/how-to-create-a-po-language-translation#more-631
 * @return void
 */
function WPML_i18n_init() {
    $pluginDir = dirname(plugin_basename(__FILE__));
    load_plugin_textdomain('wp-mail-logging', false, $pluginDir . '/languages/');
}


//////////////////////////////////
// Run initialization
/////////////////////////////////

// First initialize i18n
WPML_i18n_init();


// Next, run the version check.
// If it is successful, continue with initialization for this plugin
if (WPML_PhpVersionCheck()) {
    // Only init and run the init function if we know PHP version can parse it
    require __DIR__ . '/autoload.php';

    // Create a new instance of the autoloader
    $loader = new \WPML_Psr4AutoloaderClass();

    // Register this instance
    $loader->register();

    // Add our namespace and the folder it maps to
    $loader->addNamespace('No3x\\WPML\\', __DIR__ . '/src' );
    $loader->addNamespace('No3x\\WPML\\ORM\\', __DIR__ . '/lib/vendor/brandonwamboldt/wp-orm/src');
    $loader->addNamespace('No3x\\WPML\\Pimple\\', __DIR__ . '/lib/vendor/pimple/pimple/src');
    if( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    WPML_Init::getInstance()->init( __FILE__ );
}
