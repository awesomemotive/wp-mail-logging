<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . WPINC . '/class-wp-list-table.php' );
}

class Email_Logging_ListTable extends WP_List_Table {
	
	function __construct(){
		global $status, $page, $hook_suffix;
		parent::__construct( array(
			'singular' => __( 'Email', 'wml' ), //singular name of the listed records
			'plural' => __( 'Email', 'wml' ), 	//plural name of the listed records
			'ajax' => false 									//does this table support ajax?
		) );
	}	
	
	function get_columns(){
		$columns = array(
				'mail_id' 		=> __( 'ID', 'wml'),
				'to'    => __( 'To', 'wml'),
				'subject'      => __( 'Subject', 'wml'),
		);
		return $columns;
	}
	
	function prepare_items() {
		global $wpdb;
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$example_data = array(
				array('mail_id' => 1,'to' => 'Quarter Share', 'subject' => 'Nathan Lowell'),
				array('mail_id' => 2,'to' => 'BlBUafas', 'subject' => 'aads'),
				array('mail_id' => 3,'to' => 'Dasdsad', 'subject' => 'Bas'),
				
		);
		
		//TODO prefix from AL
		$tableName = 'wp_no3x_wpml_plugin_mail_logging';
		$example_data = $wpdb->get_results("SELECT * FROM `$tableName`", ARRAY_A);
		
		$this->items = $example_data;
	}
	
	function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'mail_id':
			case 'to':
			case 'subject':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
		}
	}
	
	function no_items() {
		_e( 'No ' . $this->_args['singular'] . ' logged yet.' );
		return;
	}
}

?>