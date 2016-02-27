<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 27.02.2016
 * Time: 14:07
 */

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail as Mail;

class WPML_REST {

	public function addActionsAndFilters() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		global $wpml_settings;
		if( boolval( $wpml_settings['rest-endpoint-mails-enabled'] ) ) {
			add_action( 'rest_api_init', function() {
				register_rest_route( 'wpmaillogging/v1', '/mails', array(
						array(
								'methods'         => \WP_REST_Server::READABLE,
								'callback' => array( $this, 'get_items' ),
								'permission_callback' => array( $this, 'get_items_permissions_check' ),
						),
						array(
								'methods'         => \WP_REST_Server::DELETABLE,
								'callback' => array( $this, 'delete_items' ),
								'permission_callback' => array( $this, 'get_items_permissions_check' ),
						),
				) );
				register_rest_route( 'wpmaillogging/v1', '/mail/(?P<id>\d+)', array(
						array(
								'methods'         => \WP_REST_Server::READABLE,
								'callback' => array( $this, 'get_item' ),
								'permission_callback' => array( $this, 'get_items_permissions_check' ),
						),
						array(
								'methods'         => \WP_REST_Server::DELETABLE,
								'callback' => array( $this, 'delete_item' ),
								'permission_callback' => array( $this, 'get_items_permissions_check' ),
						),
				) );
			} );
		}
	}

	/**
	 * Check if a given request has access to read /mails.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		global $wpml_settings;

		if ( ! boolval( $wpml_settings['rest-endpoint-mails-auth-enabled'] ) ) {
			return true;
		}

		if ( ! current_user_can( $wpml_settings['can-see-submission-data'] ) ) {
			return new \WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to see this data. (Authorization required)' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get all mails.
	 *
	 * @return array mails.
	 */
	function get_items() {
		$mails = Mail::all();
		$response = array();
		foreach ( $mails as $mail ) {
			/** @var $mail Mail */
			$response[] = $mail->to_array();
		}
		return $response;
	}

	/**
	 * Deletes all mails.
	 *
	 * @return array Mail ids of mails deleted..
	 */
	function delete_items() {
		$mails = Mail::all();
		$response = array();
		foreach ( $mails as $mail ) {
			/** @var $mail Mail */
			$mail->delete();
			$response[] = $mail->get_mail_id();
		}
		return $response;
	}

	/**
	 * Get mail by id.
	 * @param array $args Arguments.
	 * @return array|\WP_Error returns mail or WP_Error if not found.
	 */
	function get_item( $args ) {
		$id = $args['id'];
		$mail = Mail::find_one( $id );
		if ( false !== $mail ) {
			return $mail->to_array();
		}
		return new \WP_Error( 'Not found', __( 'Mail not found' ), array( 'status' => 404 ) );
	}

	/**
	 * Delete mail by id.
	 * @param array $args Arguments.
	 * @return bool|\WP_Error Returns true if delete successful or false if it was not. Returns WP_Error if not found.
	 */
	function delete_item( $args ) {
		$mail = Mail::find_one( $args['id'] );
		if ( false !== $mail ) {
			return $mail->delete();
		}
		return new \WP_Error( 'Not found', __( 'Mail not found' ), array( 'status' => 404 ) );
	}
}