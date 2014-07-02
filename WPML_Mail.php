<?php

class WPML_Mail {
	
	private $mail_table;
	private $mail_id;
	private $timestamp;
	private $receiver;
	private $subject;
	private $message;
	private $headers;
	private $attachments;
	private $plugin_version;
	
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
	}
	
	private function getAllowedProperties() {
		$allowed = false;
		foreach ( get_class_vars( get_class( $this) ) as $property => $value ) {
			switch ($property) {
				case 'mail_id':
				case 'timestamp':
				case 'receiver':
				case 'subject':
				case 'message':
				case 'headers':
				case 'attachments':
				case 'plugin_version':
					$allowed[$property] = $value;
					break;
			}
		}
		return $allowed;
	}
	
	private function getFormatOfProperties() {
		$types = false;
		foreach( $this->getAllowedProperties() as $property => $value ) {
			switch ($property) {
				case 'mail_id':
				case 'attachments':
					$types[$property] = '%d';
					break;
				case 'timestamp':
				case 'receiver':
				case 'subject':
				case 'message':
				case 'headers':
				case 'plugin_version':
					$types[$property] = '%s';
					break;
			}
		}
		return $types;
	}
	
	function __construct( $map ) {
		foreach ( $map as $key => $value ) {
			if ( property_exists ( $this , $key ) ) {
				$this->$key = $value;
				if( strpos( $key, 'timestamp' ) !== false ) {
					$this->$key = current_time('mysql');
					echo $key;
				}
			}
		}
		
		$this->mail_table = WPML_Plugin::getTablename('mails');
		
		$this->load();
	}
	
	private function load() {
		global $wpdb;
		$result = false;
		
		if( isset( $this->mail_id ) && $this->mail_id > 0 ) {
			
			$mail_row = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM $this->mail_table WHERE mail_id = %d",
					$this->mail_id
			) );
			if ( isset( $mail_row ) ) {
				$result = new self( $mail_row );
			}
		}
		return $result;
	}
	
	private function update() {
		global $wpdb;
		
		$wpdb->update(
				$this->mail_table,
				$this->getAllowedProperties(),
				array( 'mail_id' => $this->mail_id ),
				$this->getFormatOfProperties(),
				array( '%d' )
		);
	}
	
	private function create() {
		global $wpdb;
		
		// ohh.. we don't have a id here ..
		$allowed = $this->getAllowedProperties();
		unset( $allowed['mail_id'] );
		
		$format = $this->getFormatOfProperties();
		unset( $format['mail_id']);
		
		$wpdb->insert(
				$this->mail_table,
				$allowed,
				$format
		);

		print_r( $wpdb->last_query );
		
		echo $wpdb->insert_id;
	}
	
	function save() {
		global $wpdb;
		
		if( isset( $this->mail_id ) ) {
			$this->update();			
		} else {
			$this->create();
		}
	}
}