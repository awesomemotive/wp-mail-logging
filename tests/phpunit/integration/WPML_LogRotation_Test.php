<?php

namespace No3x\WPML\Tests\Integration;

use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\WPML_LogRotation;
use Arrayzy\ImmutableArray;

/**
 * Tests for LogRotation
 * @author No3x
 * @since 1.6.0
 * Tests are written in the AAA-Rule
 * There are three basic sections for our test: Arrange, Act, and Assert.
 */
class WPML_LogRotation_Test extends WPML_IntegrationTestCase {

	private function prepareMessages( $amount ) {
		$to = 'email@example.com';
		$subject = "Message Nr. %d";
		$message = "This is a sample message. It is the %d. of this kind.";

		for( $i = 0; $i < $amount; $i++ ) {
			wp_mail(
				$to,
				sprintf($subject, $i),
				sprintf($message, $i)
			);
		}
		// Check if preparation worked fine
		$this->assertEquals($this->count_mails(), $amount);
	}

	private function count_mails() {
		return Mail::query()->find(true);
	}

	/**
	 * Query some mails.
	 * @param int $amount number of mails to query.
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
	 * Get latest mail.
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
	 * Get oldest mail.
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

	/**
	 * Test limitNumberOfMailsByAmount - Check if the number of kept messages is correct.
	 * The LogRotation supports the limitation of stored mails by amount.
	 * This test checks if the amount of left/deleted mails is correct.
	 * @since 1.6.0
	 * @see WPML_LogRotation::limitNumberOfMailsByAmount
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
	 * Test limitNumberOfMailsByAmount - Check if old messages where deleted first.
	 * The LogRotation supports the limitation of stored mails by amount.
	 * This test checks if old messages are deleted first by asserting that after the policy is run the oldest mail is gone.
	 * @since 1.6.0
	 * @see WPML_LogRotation::limitNumberOfMailsByAmount
	 */
	function test_limitNumberOfMailsByAmount_order() {
		global $wpml_settings;
		$amount = 10;
		$keep = 3;
		$this->prepareMessages( $amount );
		$wpml_settings['log-rotation-limit-amout'] = '1';
		$wpml_settings['log-rotation-limit-amout-keep'] = $keep;
		$oldest_mail_id = $this->oldest_mail()->get_mail_id();

		WPML_LogRotation::limitNumberOfMailsByAmount();

		// Assert oldest mail is gone.
		$this->assertFalse( Mail::find_one( $oldest_mail_id ) );
	}

	/**
	 * Test limitNumberOfMailsByTime - Check if all old messages where deleted.
	 * The LogRotation supports the limitation of stored mails by date.
	 * This test checks of old messages are deleted after given time by comparing the amount of mails.
	 * @since 1.6.0
	 * @see WPML_LogRotation::limitNumberOfMailsByTime
	 */
	function test_limitNumberOfMailsByTime_order() {
		global $wpml_settings;
		$amount = 10;
		$old = 3;
		$days = 5;
		$this->prepareMessages( $amount );
		$wpml_settings['log-rotation-delete-time'] = '1';
		$wpml_settings['log-rotation-delete-time-days'] = $days;

		// Make #$old mails #$days older:
		foreach( $this->query_some_mails( $old ) as $mail ) {
			$mail->set_timestamp( gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - ($days+1) * DAY_IN_SECONDS  ) ) )
				 ->save();
		}

		WPML_LogRotation::limitNumberOfMailsByTime();

		// Assert that there are just $amount-$old mails left.
		$this->assertEquals( $amount-$old, $this->count_mails() );
	}
}
