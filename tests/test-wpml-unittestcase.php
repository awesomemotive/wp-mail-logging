<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Tests\Helper\WPML_UnitTestCase;

class WPML_Test_WPML_UnitTestCase extends WPML_UnitTestCase {

	function test_PluginInitialization() {
		$this->assertFalse( null === $this->getPlugin() );
		$this->assertInstanceOf( '\\No3x\\WPML\\WPML_Plugin', $this->getPlugin() );
	}

} 