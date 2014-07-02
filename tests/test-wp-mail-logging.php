<?php

/**
 * @author No3x
 * Tests are written in the AAA-Rule
 * There are three basic sections to our test: Arrange, Act, and Assert.
 */
class WPML_Plugin_Test extends WP_UnitTestCase {
	
	private $plugin;

	function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['WPML_Plugin'];
	}
	
	function test_PluginInitialization() {
		$this->assertFalse( null == $this->plugin );
	}
	
	function test_getTablename() {
		global $wpdb;
		
		// Arrange
		$tableName = 'testTable';
		// Act
		$prefixed = WPML_Plugin::getTablename( $tableName );
		// Assert
		$this->assertEquals( $wpdb->prefix . 'wpml_testTable', $prefixed );
	}
	
}
