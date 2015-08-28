<?php

namespace No3x\WPML\Tests;

/**
 * Test uninstallation.
 */

/**
 * Plugin uninstall test case.
 *
 * Be sure to add "@group uninstall", so that the test will run only as part of the
 * uninstall group.
 *
 * @group uninstall
 */
class WPML_Uninstall_Plugin_Test extends WP_Plugin_Uninstall_UnitTestCase {

	//
	// Protected properties.
	//

	/**
	 * The full path to the main plugin file.
	 *
	 * @type string $plugin_file
	 */
	protected $plugin_file;

	/**
	 * The plugin's install function.
	 *
	 * @type callable $install_function
	 */
	protected $install_function = 'install';

	protected $uninstall_function = 'uninstall';

	//
	// Public methods.
	//

	/**
	 * Set up for the tests.
	 */
	public function setUp() {

		$this->install_function = array( $GLOBALS['WPML_Plugin'], 'install');
		$this->uninstall_function = array( $GLOBALS['WPML_Plugin'], 'uninstall');

		// You must set the path to your plugin here.
		$this->plugin_file = dirname( dirname( __FILE__ ) ) . '/wp-mail-logging_init.php';

		// Don't forget to call the parent's setUp(), or the plugin won't get installed.
		parent::setUp();
	}

	/**
	 * Test installation and uninstallation.
	 */
	public function test_uninstall() {
		global $wpdb, $wpml_settings;

		// Inject Setting
		$wpml_settings['delete-on-deactivation'] = '1';

		/*
		 * First test that the plugin installed itself properly.
		 */

		// Check that a database table was added.
		$this->assertTableExists( $wpdb->prefix . 'wpml_mails' );

		// Check that an option was added to the database.
		$this->assertEquals( '1', get_option( 'WPML_Plugin__installed' ) );

		/*
		 * Now, test that it uninstalls itself properly.
		 */

		// You must call this to perform uninstallation.
		$this->uninstall();

		// Check that the table was deleted.
		$this->assertTableNotExists( $wpdb->prefix . 'wpml_mails' );

		// Check that all options with a prefix was deleted.
		$this->assertNoOptionsWithPrefix( 'wpml_' );

		$this->assertFalse( get_option( 'WPML_Plugin__installed' ) );

		// Same for usermeta and comment meta.
		$this->assertNoUserMetaWithPrefix( 'wpml_' );
		$this->assertNoCommentMetaWithPrefix( 'wpml_' );
	}
}