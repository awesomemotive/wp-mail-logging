<?php

/**
 * @author No3x
 * Tests are written in the AAA-Rule
 * There are three basic sections to our test: Arrange, Act, and Assert.
 */
class WPML_Mail_Test extends WP_UnitTestCase {
	
	private $mail;

	function setUp() {
		parent::setUp();
		
		$map_simple_fine = array(
				'subject' => 'Hello World'
		);
		
		$this->mail = new WPML_Mail( $map_simple_fine );
	}
	
	function test_construct() {
		$this->assertEquals( 'Hello World', $this->mail->subject );
		
		$this->mail->save();
	}
	
	
}
