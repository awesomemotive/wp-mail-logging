<?php

namespace No3x\WPML\Tests\Unit;

use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_Plugin;

/**
 * @author No3x
 * Tests are written in the AAA-Rule
 * There are three basic sections for our test: Arrange, Act, and Assert.
 */
class WPML_Plugin_Test extends \PHPUnit_Framework_TestCase {

    function test_getTablename() {
        global $wpdb;

        // Arrange
        $tableName = 'testTable';
        // Act
        $prefixed = WPML_Plugin::getTablename( $tableName );
        // Assert
        $this->assertEquals( $wpdb->prefix . 'wpml_testTable', $prefixed );
    }

	function test_version_compare() {
		$this->assertEquals( 1, version_compare( '1.6.0', '1.4.0' ) );
		$this->assertEquals( 1, version_compare( '1.4.0', '1.4.0_betaR1' ) );
		$this->assertEquals( 1, version_compare( '1.4.0_betaR2', '1.4.0_betaR1' ) );
		$this->assertEquals( 1, version_compare( '1.6.0_betaR2', '1.4.0_betaR1' ) );
	}

	function test_getClass() {
        $this->assertEquals( 'No3x\WPML\WPML_Plugin', WPML_Plugin::getClass());
    }
}
