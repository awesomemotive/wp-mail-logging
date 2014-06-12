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
	
	/* (non-PHPdoc)
	 * @see WP_List_Table::get_columns()
	 */
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
		
		$columns = apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS, $columns );
		
		$special = array('_title', 'comment', 'media', 'name', 'title', 'username', 'blogname');
		
		foreach ( $special as $key ) {
			if( array_key_exists( $key, $columns ) ) {
				echo "You should avoid $key as keyname since it is treated by WordPress specially: Your table would still work, but you won't be able to show/hide the columns. You can prefix your columns!";
				break;
			}
		}
		
		return $columns;
	}
	
	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	function get_hidden_columns() {
		return array( 
			'plugin_version', 
			'mail_id' 
		);
	}
	
	/* (non-PHPdoc)
	 * @see WP_List_Table::get_columns()
	 */
	function prepare_items() {
		global $wpdb;
		$tableName = _get_tablename('mails');
		
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns($screen);
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$this->process_bulk_action();
		
		$per_page = $this->get_items_per_page( 'per_page', 25 );
		$current_page = $this->get_pagenum();
		$total_items = $wpdb->get_var("SELECT COUNT(*) FROM  `$tableName`");
		$limit = $per_page*$current_page;
		//TODO: make option for default order
		$orderby_default = "mail_id";
		$order_default = "desc";
		$orderby = ( !empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : $orderby_default;
		$order = ( !empty($_GET['order'] ) ) ? $_GET['order'] : $order_default;
		
		$found_data = $wpdb->get_results("SELECT * FROM `$tableName` ORDER BY $orderby $order LIMIT $limit", ARRAY_A);
		
		$dataset = array_slice( $found_data,( ( $current_page-1 )* $per_page ), $per_page );
		
		$this->set_pagination_args( array(
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		) );
		$this->items = $dataset;
	}
	
	
	/**
	 * Renders the cell. 
	 * Note: We can easily add filter for all columns if you want to / need to manipulate the content. (currently only additional column manipulation is supported)
	 * @param array $item
	 * @param string $column_name
	 * @return string The cell content
	 */
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
				// if we don't know this column maybe a hook does - if no hook extracted data (string) out of the array we can avoid the output of 'Array()' (array)
				return (is_array( $res = apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS_RENDER, $item, $column_name ) ) ) ? "" : $res;
		}
	}
	
	/* (non-PHPdoc)
	 * @see WP_List_Table::get_bulk_actions()
	 */
	function get_bulk_actions() {
		$actions = array(
			'delete'    => 'Delete'
		);
		return $actions;
	}
	
	function process_bulk_action() {
		global $wpdb;
		$name = $this->_args['singular'];
		$tableName = _get_tablename('mails');
		
		//Detect when a bulk action is being triggered...
		if( 'delete' == $this->current_action() ) {
			foreach($_REQUEST[$name] as $item_id) {
				$wpdb->query("DELETE FROM `$tableName` WHERE mail_id = $item_id");
			}
		}
	}
	
	
	/**
	 * Render the cb column
	 * @param object $item The current item
	 * @return string the rendered cb cell content
	 */
	function column_cb($item) {
		$name = $this->_args['singular'];
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />', $name, $item['mail_id']
		);
	}
	
	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	function get_sortable_columns() {
		$sortable_columns = array(
			// column_name => array( 'display_name', true[asc] | false[desc] )
			'mail_id'  => array('mail_id', false),
			'timestamp' => array('timestamp', true),
			'to' => array('to', true),
			'subject' => array('subject', true),
			'message' => array('message', true),
			'headers' => array('headers', true),
			'attachments' => array('attachments', true),
			'plugin_version' => array('plugin_version', true)
		);
		return $sortable_columns;
	}
	
	/* (non-PHPdoc)
	 * @see WP_List_Table::no_items()
	 */
	function no_items() {
		_e( 'No ' . $this->_args['singular'] . ' logged yet.' );
		return;
	}
}

?>
