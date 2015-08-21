<?php

// Exit if accessed directly
if(!defined( 'ABSPATH' )) exit;

/**
 * @author No3x
 * @since 1.0
 * The Plugin provides mechanisms to extend the displayed data.
 * This class is not an API class. It is just an example.
 */
class WPML_API_Example {
	
	// require_once('WPML_API_Example.php');
	// $aAPI = new WPML_API_Example();
	
	function __construct() {
		
		// In this example we are going to add a column 'test' in add_column.
		add_filter( WPML_Plugin::HOOK_LOGGING_COLUMNS, array(&$this, 'add_column' ) );
		add_filter( WPML_Plugin::HOOK_LOGGING_COLUMNS_RENDER, array(&$this, 'render_column' ), 10, 2 );
		
	}	
	
	/**
	 * Is called when List Table is gathering columns.
	 * @since 1.0
	 * @param array $columns Array of columns
	 * @return array $columns Updated array of columns
	 */
	public function add_column( $columns ) {
		return $columns = array_merge( $columns, 
			array('test'	=> __( 'test', 'wml' ) )
			//,array('test2'	=> __( 'test2', 'wml' ) ) // ...
		);
	}
	
	
	/**
	 * Is called when the List Table could not find the column. So we can hook in and modify the column.
	 * @since 1.0
	 * @param array $item A singular item (one full row's worth of data)
	 * @param array $column_name The name/slug of the column to be processed
	 * @return string Text or HTML to be placed inside the column <td>
	 */
	public function render_column( $item, $column_name ) {
	
		switch( $column_name ) {
			case 'test':
				return "display relevant data. item contains all information you need about the row. You can process the data and add the result to this column.";
			default:
				return "";
		}
	}
}