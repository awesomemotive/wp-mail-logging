<?php

class WPML_Plugin_Test extends WP_UnitTestCase {
	
	private $plugin;
	
	function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['WPML_Plugin'];
	}
	
	function testSample() {
		// replace this with some actual testing code
		$this->assertTrue( true );
	}
	
}

?>	