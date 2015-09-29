<?php

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail as Mail;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'inc/class-wp-list-table.php' );
}

/**
 * Renders the mails in a table list.
 * @author No3x
 * @since 1.0
 */
class Email_Logging_ListTable extends \WP_List_Table {

	const NONCE_LIST_TABLE = 'wpml-list_table';

	/**
	 * Initializes the List Table
	 * @since 1.0
	 */
	function __construct() {
		global $status, $page, $hook_suffix;

		parent::__construct( array(
			'singular' 	=> 'email', 	// singular name of the listed records
			'plural' 	=> 'emails',	// plural name of the listed records
			'ajax' 		=> false,		// does this table support ajax?
		) );
	}

	/**
	 * Is displayed if no item is available to render
	 * @since 1.0
	 * @see WP_List_Table::no_items()
	 */
	function no_items() {
		_e( 'No email found.', 'wpml' );
		return;
	}

	/**
	 * Defines the available columns.
	 * @since 1.0
	 * @see WP_List_Table::get_columns()
	 */
	function get_columns() {
		$columns = array(
			'cb'				=> '<input type="checkbox" />',
			'mail_id'			=> __( 'ID', 'wpml' ),
			'timestamp'			=> __( 'Time', 'wpml' ),
			'receiver'			=> __( 'Receiver', 'wpml' ),
			'subject'			=> __( 'Subject', 'wpml' ),
			'message'			=> __( 'Message', 'wpml' ),
			'headers'			=> __( 'Headers', 'wpml' ),
			'attachments'		=> __( 'Attachments', 'wpml' ),
			'plugin_version'	=> __( 'Plugin Version', 'wpml' ),
		);

		// Give a plugin the chance to edit the columns.
		$columns = apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS, $columns );

		$reserved = array( '_title', 'comment', 'media', 'name', 'title', 'username', 'blogname' );

		// Show message for reserved column names.
		foreach ( $reserved as $reserved_key ) {
			if ( array_key_exists( $reserved_key, $columns ) ) {
				echo "You should avoid $reserved_key as keyname since it is treated by WordPress specially: Your table would still work, but you won't be able to show/hide the columns. You can prefix your columns!";
				break;
			}
		}
		return $columns;
	}

	/**
	 * Define which columns are hidden
	 * @since 1.0
	 * @return Array
	 */
	function get_hidden_columns() {
		return array(
			'plugin_version',
			'mail_id',
		);
	}

	/**
	 * Sanitize orderby parameter.
	 * @s
	 * @return string sanitized orderby parameter
	 */
	private function sanitize_orderby() {
		return WPML_Utils::sanitize_expected_value( ( !empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : null, $this->get_sortable_columns(), 'mail_id');
	}

	/**
	 * Sanitize order parameter.
	 * @return string sanitized order parameter
	 */
	private function sanitize_order() {
		return WPML_Utils::sanitize_expected_value( ( !empty( $_GET['order'] ) ) ? $_GET['order'] : null, array('desc', 'asc'), 'desc');
	}

	/**
	 * Prepares the items for rendering
	 * @since 1.0
	 * @param string|boolean $search string you want to search for. Default false.
	 * @see WP_List_Table::prepare_items()
	 */
	function prepare_items( $search = false ) {
		$orderby = $this->sanitize_orderby();
		$order = $this->sanitize_order();

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$per_page = $this->get_items_per_page( 'per_page', 25 );
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		$total_items = Mail::query()
			->search( $search )
			->find( true );

		$mails = Mail::query()
			->search( $search )
			->sort_by( $orderby )
			->order( $order )
			->limit( $per_page )
			->offset( $offset )
			->find();

		foreach ( $mails as $mail ) {
			/* @var $mail Mail */
			$this->items[] = $mail->to_array();
		}

		$this->set_pagination_args( array(
			'total_items' => $total_items, // The total number of items.
			'per_page'    => $per_page, // Number of items per page.
		) );
	}

	/**
	 * Renders the cell.
	 * Note: We can easily add filter for all columns if you want to / need to manipulate the content. (currently only additional column manipulation is supported)
	 * @since 1.0
	 * @param array  $item The current item.
	 * @param string $column_name The current column name.
	 * @return string The cell content
	 */
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'mail_id':
			case 'timestamp':
			case 'subject':
			case 'message':
			case 'headers':
			case 'attachments':
			case 'plugin_version':
			case 'receiver':
				return $item[ $column_name ];
			default:
				// If we don't know this column maybe a hook does - if no hook extracted data (string) out of the array we can avoid the output of 'Array()' (array).
				return ( is_array( $res = apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS_RENDER, $item, $column_name ) ) ) ? '' : $res;
		}
	}

	/**
	 * Sanitize message to remove unsafe html.
	 * @since 1.6.0
	 * @param string $message unsafe message.
	 * @return string safe message.
	 */
	function sanitize_message( $message ) {
		$allowed_tags = wp_kses_allowed_html( 'post' );
		$allowed_tags['a']['data-message'] = true;
		$allowed_tags['style'][''] = true;
		return wp_kses( $message, $allowed_tags );
	}

	/**
	 * Renders the message column.
	 * @since 1.3
	 * @param array $item The current item.
	 * @return void|string
	 */
	function column_message( $item ) {
		if ( empty( $item['message'] ) ) {
			return '';
		}
		if(strstr($item['headers'],"Content-Type: text/html")){
			$content = $this->render_mail( $item );
		} else {
			$content = "<pre>".$this->sanitize_message( $this->render_mail( $item ) )."</pre>";
		}
		$message = '<a class="wp-mail-logging-view-message button button-secondary" href="#" data-message="' . htmlentities( $content )  . '">View</a>';
		return $message;
	}

	/**
	 * Renders the timestamp column.
	 * @since 1.5.0
	 * @param array $item The current item.
	 * @return void|string
	 */
	function column_timestamp( $item ) {
		return date_i18n( apply_filters( 'wpml_get_date_time_format', '' ), strtotime( $item['timestamp'] ) );
	}

	/**
	 * Renders the attachment column.
	 * @since 1.3
	 * @param array $item The current item.
	 * @return string The attachment column.
	 */
	function column_attachments( $item ) {
		$attachment_append = '';
		$attachments = explode( ',\n', $item['attachments'] );
		$attachments = is_array( $attachments ) ? $attachments : array( $attachments );
		foreach ( $attachments as $attachment ) {
			// $attachment can be an empty string ''.
			if ( ! empty( $attachment ) ) {
				$filename = basename( $attachment );
				$attachment_path = WP_CONTENT_DIR . $attachment;
				$attachment_url = WP_CONTENT_URL . $attachment;

				if ( is_file( $attachment_path ) ) {
					$attachment_append .= '<a href="' . $attachment_url . '" title="' . $filename . '">' . WPML_Utils::generate_attachment_icon( $attachment_path ) . '</a> ';
				} else {
					$message = sprintf( __( 'Attachment %s is not present', 'wpml' ), $filename );
					$attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
				}
			}
		}
		return $attachment_append;
	}

	/**
	 * Renders all components of the mail.
	 * @since 1.3
	 * @param array $item The current item.
	 * @return string The mail as html
	 */
	function render_mail( $item ) {
		$mailAppend = '';
		foreach ( $item as $key => $value ) {
			if ( array_key_exists( $key, $this->get_columns() ) && ! in_array( $key, $this->get_hidden_columns() ) ) {
				$display = $this->get_columns();
				$column_name = $key;
				$mailAppend .= "<span class=\"title\">{$display[$key]}: </span>";
				if ( 'message' !== $column_name  && method_exists( $this, 'column_' . $column_name ) ) {
					$mailAppend .= call_user_func( array( $this, 'column_' . $column_name ), $item );
				} else {
					$mailAppend .= $this->column_default( $item, $column_name );
				}
			}
		}
		return $mailAppend;
	}

	/**
	 * Defines available bulk actions.
	 * @since 1.0
	 * @see WP_List_Table::get_bulk_actions()
	 */
	function get_bulk_actions() {
		$actions = array(
			'delete'    => 'Delete',
		);
		return $actions;
	}

	/**
	 * Processes bulk actions.
	 * @since 1.0
	 */
	function process_bulk_action() {
		if ( false === $this->current_action() ) {
			return;
		}

		if ( check_admin_referer( Email_Logging_ListTable::NONCE_LIST_TABLE, Email_Logging_ListTable::NONCE_LIST_TABLE . '_nonce' ) ) {
			$name = $this->_args['singular'];

			// Detect when a bulk action is being triggered.
			if ( 'delete' === $this->current_action() ) {
				foreach ( $_REQUEST[$name] as $item_id ) {
					$mail = Mail::find_one( $item_id );
					if ( false !== $mail ) {
						$mail->delete();
					}
				}
			}
		}
	}

	/**
	 * Render the cb column
	 * @since 1.0
	 * @param array $item The current item.
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
	 * @since 1.0
	 * @return Array
	 */
	function get_sortable_columns() {
		return array(
			// Description: column_name => array( 'display_name', true[asc] | false[desc] ).
			'mail_id'  		=> array( 'mail_id', false ),
			'timestamp' 	=> array( 'timestamp', true ),
			'receiver' 		=> array( 'receiver', true ),
			'subject' 		=> array( 'subject', true ),
			'message' 		=> array( 'message', true ),
			'headers' 		=> array( 'headers', true ),
			'attachments' 	=> array( 'attachments', true ),
			'plugin_version'=> array( 'plugin_version', true ),
		);
	}
}
