<?php

namespace No3x\WPML\Tests\Unit;

use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;

class WPML_IntegrationTestCase_Test extends WPML_IntegrationTestCase {

	function test_PluginInitialization() {
		$this->assertFalse( null === $this->getPlugin() );
		$this->assertInstanceOf( '\\No3x\\WPML\\WPML_Plugin', $this->getPlugin() );
	}

}
