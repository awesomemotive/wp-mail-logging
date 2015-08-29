<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Tests\Helper\WPML_UnitTestCase;
use No3x\WPML\WPML_Plugin;
use No3x\WPML\WPML_InstallIndicator;

/**
 * @author No3x
 * Tests are written in the AAA-Rule
 * There are three basic sections for our test: Arrange, Act, and Assert.
 */
class WPML_Plugin_CoreSettings extends WPML_UnitTestCase {

	function test_getInstalled() {

		$this->assertEquals( 'WPML_Plugin__installed', $this->getPlugin()->prefix( WPML_InstallIndicator::optionInstalled ) );
		$this->assertEquals( 'WPML_Plugin__version', $this->getPlugin()->prefix( WPML_InstallIndicator::optionVersion ) );

	}
}
