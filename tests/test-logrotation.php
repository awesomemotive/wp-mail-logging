<?php

use WordPress\ORM\Model\WPML_Mail as Mail;
use Arrayzy\ImmutableArray;

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
	 * Test limitNumberOfMailsByAmount - Check if the number of kept messages is correct.
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
	 * Test limitNumberOfMailsByAmount - Check if old messages where deleted first
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

	function test_limitNumberOfMailsByTime_order() {
		global $wpml_settings;
		$amount = 10;
		$old = 3;
		$days = 5;
		$this->prepareMessages( $amount );
		$wpml_settings['log-rotation-delete-time'] = '1';
		$wpml_settings['log-rotation-delete-time-days'] = $days;

		foreach( $this->query_some_mails( $old ) as $mail ) {
			// Make mails $days older
			$mail->set_timestamp( gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) + $days * DAY_IN_SECONDS ) ) )
				 ->save();
		}
		WPML_LogRotation::limitNumberOfMailsByTime();

		$this->assertEquals( $amount-$old, $this->count_mails() );

	}

	private function count_mails() {
		return Mail::query()->find(true);
	}

	/**
	 * @param $amount
	 * @return Mail[] array
	 */
	private function query_some_mails( $amount ) {
		return Mail::query()
			->sort_by( Mail::get_primary_key() )
			->order( 'desc' )
			->limit( $amount )
			->find();
	}

	/**
	 * @return Mail
	 */
	private function latest_mail() {
		return ImmutableArray::create(
			Mail::query()
				->sort_by( Mail::get_primary_key() )
				->order( 'desc' )
				->limit(1)
				->find()
		)->first();
	}
	/**
	 * @return Mail
	 */
	private function oldest_mail() {
		return ImmutableArray::create(
			Mail::query()
				->sort_by( Mail::get_primary_key() )
				->order( 'asc' )
				->limit(1)
				->find()
		)->first();
	}
}
