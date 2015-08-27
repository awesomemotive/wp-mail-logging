<?php

use WordPress\ORM\Model\WPML_Mail as Mail;

/**
 * Tests for LogRotation
 * @author No3x
 * Tests are written in the AAA-Rule
 * There are three basic sections for our test: Arrange, Act, and Assert.
 */
class WPML_LogRotation_Test extends WP_UnitTestCase {
	
	private $plugin;

	function setUp() {
		parent::setUp();
		$this->plugin = &$GLOBALS['WPML_Plugin'];
		if( !isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = 'vvv';
		}
	}

	function test_PluginInitialization() {
		$this->assertFalse( null == $this->plugin );
	}

	private function prepareMessages( $amount ) {
		$to = 'email@example.com';
		$subject = "Message Nr. %d";
		$message = "This is a sample message. It is the %d. of this kind.";

		for( $i = 0; $i < $amount; $i++) {
			wp_mail(
				$to,
				sprintf($subject, $i),
				sprintf($message, $i)
			);
		}
		// Check if preperation worked fine
		$this->assertEquals($this->count_mails(), $amount);
	}

	/**
	 * Check if the number of kept messages is correct.
	 */
	function test_limitNumberOfMailsByAmount_count() {
		global $wpml_settings;
		$amount = 10;
		$keep = 3;
		$this->prepareMessages( $amount );

		$wpml_settings['log-rotation-limit-amout'] = '1';
		$wpml_settings['log-rotation-limit-amout-keep'] = $keep;

		$this->assertEquals($this->count_mails(), $amount);
		WPML_LogRotation::limitNumberOfMailsByAmount();
		$this->assertEquals($this->count_mails(), $keep);
	}

	/**
	 * Check if old messages where deleted first
	 */
	function test_limitNumberOfMailsByAmount_order() {
		global $wpml_settings;
		$amount = 10;
		$keep = 3;
		$this->prepareMessages( $amount );
		$wpml_settings['log-rotation-limit-amout'] = '1';
		$wpml_settings['log-rotation-limit-amout-keep'] = $keep;
		$lates_mail = $this->latest_mail();
		$oldest_mail = $this->oldest_mail();

		WPML_LogRotation::limitNumberOfMailsByAmount();
		$this->assertLessThan( $lates_mail->get_mail_id(), $oldest_mail->get_mail_id() );
	}

	private function count_mails() {
		return Mail::query()->find(true);
	}

	/**
	 * @return Mail
	 */
	private function latest_mail() {
		$mails = Mail::query()
			->sort_by('mail_id')
			->order('desc')
			->limit(1)
			->find();
		return reset( $mails );
	}
	/**
	 * @return Mail
	 */
	private function oldest_mail() {
		$mails = Mail::query()
			->sort_by('mail_id')
			->order('asc')
			->limit(1)
			->find();
		return reset( $mails );
	}
}
