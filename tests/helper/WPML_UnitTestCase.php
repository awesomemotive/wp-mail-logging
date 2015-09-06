<?php

namespace No3x\WPML\Tests\Helper;

use No3x\WPML\WPML_Init;

class WPML_UnitTestCase extends \WP_UnitTestCase {

	/** @var WPML_Plugin */
	private $plugin;

	/**
	 * @return \No3x\WPML\WPML_Plugin
	 */
	public function getPlugin() {
		return $this->plugin;
	}

	function setUp() {
		parent::setUp();

		$this->plugin = apply_filters('wpml_get_di_service', 'plugin' );

		if( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = 'vvv';
		}
	}

	function tearDown() {
		parent::tearDown();
	}

	function test_fuuuuuu() {
		// 'This test is here to avoid "No Tests found in ..." Since I was not able to exclude it.
	}
} 