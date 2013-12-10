<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . WPINC . '/class-wp-list-table.php' );
}

class Email_Logging_ListTable extends WP_List_Table {
	
	function __construct(){
		
		global $status, $page, $hook_suffix;
		parent::__construct( array(
			'singular' 	=> __( 'Email', 'wml' ),//singular name of the listed records
			'plural' 	=> __( 'Emails', 'wml' ),//plural name of the listed records
			'ajax' 		=> false				//does this table support ajax?
		) );
		
	}	
	
	function get_columns(){
		$columns = array(
			 	'cb'        => '<input type="checkbox" />',
				'mail_id'		=> __( 'ID', 'wml'),
				'timestamp'		=> __( 'Time', 'wml'),
				'to'			=> __( 'To', 'wml'),
				'subject'		=> __( 'Subject', 'wml'),
				'message'		=> __( 'Message', 'wml'),
				'headers'		=> __( 'Headers', 'wml'),
				'attachments'	=> __( 'Attachments', 'wml'),
				'plugin_version'=> __( 'Plugin Version', 'wml')
		);
		
		return apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS, $columns );
	}
	
	function prepare_items() {
		global $wpdb;
		//TODO prefix from AL
		$tableName = 'wp_no3x_wpml_plugin_mail_logging';
		
		$columns = $this->get_columns();
		$hidden = array( 
				//'plugin_version' 
		);
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$this->process_bulk_action();
		
		$per_page = 50;
		$current_page = $this->get_pagenum();
		$total_items = $wpdb->get_var("SELECT COUNT(*) FROM  `$tableName`");
		$limit = $per_page*$current_page;
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'mail_id';
		$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
		
		$found_data = $wpdb->get_results("SELECT * FROM `$tableName` ORDER BY $orderby $order LIMIT $limit", ARRAY_A);
		
		// only ncessary because we have sample data
		$dataset = array_slice( $found_data,( ( $current_page-1 )* $per_page ), $per_page );
		
		$this->set_pagination_args( array(
				'total_items' => $total_items,                  //WE have to calculate the total number of items
				'per_page'    => $per_page                     //WE have to determine how many items to show on a page
		) );
		$this->items = $dataset;
	}
	
	
	function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'mail_id':
			case 'timestamp':
			case 'to':
			case 'subject':
			case 'message':
			case 'headers':
			case 'attachments':
			case 'plugin_version':
				return $item[ $column_name ];
			default:
				// if we don't know this coulmn maybe a hook does
				return apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS_RENDER, $item, $column_name );
		}
	}
	
	function get_bulk_actions() {
		$actions = array(
				'delete'    => 'Delete'
		);
		return $actions;
	}
	
	function process_bulk_action() {
		global $wpdb;
		$name = $this->_args['singular'];
		
		//Detect when a bulk action is being triggered...
		if( 'delete' == $this->current_action() ) {
			foreach($_REQUEST[$name] as $item_id) {
				//TODO prefix from AL
				$tableName = 'wp_no3x_wpml_plugin_mail_logging';
				$wpdb->query("DELETE FROM `$tableName` WHERE mail_id = $item_id");
			}
		}
	}
	
	function column_cb($item) {
		$name = $this->_args['singular'];
		return sprintf(
				'<input type="checkbox" name="%1$s[]" value="%2$s" />', $name, $item['mail_id']
		);
	}
	
	function get_sortable_columns() {
		$sortable_columns = array(
				'mail_id'  => array('mail_id', true),
				'timestamp' => array('timestamp',false),
				'to' => array('to',false),
				'subject' => array('subject',false),
				'message' => array('message',false),
				'headers' => array('headers',false),
				'attachments' => array('attachments',false),
				'plugin_version' => array('plugin_version',false)
		);
		return $sortable_columns;
	}
	
	function no_items() {
		_e( 'No ' . $this->_args['singular'] . ' logged yet.' );
		return;
	}
}

?>