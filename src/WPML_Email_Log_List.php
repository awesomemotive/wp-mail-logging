<?php

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Printer\ColumnFormat;
use No3x\WPML\Printer\WPML_ColumnRenderer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

require_once(ABSPATH . 'wp-admin/includes/screen.php');
require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( plugin_dir_path( __FILE__ ) . 'inc/class-wp-list-table.php' );
}

/**
 * Renders the mails in a table list.
 * @author No3x
 * @since 1.0
 */
class WPML_Email_Log_List extends \WP_List_Table implements IHooks {

    const NONCE_LIST_TABLE = 'wpml-list_table';
    /** @var WPML_Email_Resender $emailResender */
    private $emailResender;
    /** @var WPML_MessageSanitizer $messageSanitizer */
    private $messageSanitizer;
    /** @var WPML_ColumnRenderer $columnRenderer */
    private $columnRenderer;

    /**
     * Initializes the List Table
     * @since 1.0
     * @param WPML_Email_Resender $emailResender
     */
    function __construct( $emailResender ) {
        $this->emailResender = $emailResender;
        $this->messageSanitizer = new WPML_MessageSanitizer();
        $this->columnRenderer = new WPML_ColumnRenderer();
    }

    function addActionsAndFilters() {
        add_action( 'admin_init', array( $this, 'init') );
    }

    function init() {
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
        _e( 'No email found.', 'wp-mail-logging' );
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
            'mail_id'			=> __( 'ID', 'wp-mail-logging' ),
            'timestamp'			=> __( 'Time', 'wp-mail-logging' ),
            'receiver'			=> __( 'Receiver', 'wp-mail-logging' ),
            'subject'			=> __( 'Subject', 'wp-mail-logging' ),
            'message'			=> __( 'Message', 'wp-mail-logging' ),
            'headers'			=> __( 'Headers', 'wp-mail-logging' ),
            'attachments'		=> __( 'Attachments', 'wp-mail-logging' ),
            'error'		        => __( 'Error', 'wp-mail-logging' ),
            'plugin_version'	=> __( 'Plugin Version', 'wp-mail-logging' ),
        );

        /* @var $instance WPML_Plugin */
        $instance = WPML_Init::getInstance()->getService( 'plugin' );

        $switch = $instance->getSetting('display-host', false );
        if( true == $switch ) {
            $posAfterTimestamp = array_search('timestamp', array_keys($columns) ) + 1;
            $columns = array_merge(
                array_slice( $columns, 0, $posAfterTimestamp),
                [ 'host' =>  __( 'Host', 'wp-mail-logging' ) ],
                array_slice( $columns, $posAfterTimestamp )
            );
        }

        return $columns;
    }

    /**
     * Define which columns are hidden
     * @since 1.0
     * @return array
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
     * @since 1.0
     * @param array  $item The current item.
     * @param string $column_name The current column name.
     * @return string The cell content
     */
    function column_default( $item, $column_name ) {
        $column_content = $this->columnRenderer->getColumn($column_name)->render($item, ColumnFormat::FULL);
        return $this->sanitize_text($column_content);
    }

    /**
     * Sanitize text to remove unsafe html.
     * @since 1.5.1
     * @param string $message unsafe text.
     * @return string safe text.
     */
    function sanitize_text( $message ) {
        return $this->messageSanitizer->sanitize($message);
    }

    /**
     * Renders the message column.
     * @since 1.3
     * @param array $item The current item.
     * @return string
     */
    function column_message( $item ) {
        $content = $item['mail_id'];
        $message = '<a class="wp-mail-logging-view-message button button-secondary" href="#" data-mail-id="' . esc_attr( $content )  . '">View</a>';
        return $message;
    }

    /**
     * Renders all components of the mail.
     * @since 1.3
     * @param array $item The current item.
     * @return string The mail as html
     */
    function render_mail( $item ) {
        $mailAppend = '';
        foreach ( $item as $column_name => $value ) {
            if ( array_key_exists( $column_name, $this->get_columns() ) && ! in_array( $column_name, $this->get_hidden_columns() ) ) {
                $display = $this->get_columns();
                $title = "<span class=\"title\">{$display[$column_name]}: </span>";
                $content = '';
                if ( 'message' !== $column_name  && method_exists( $this, 'column_' . $column_name ) ) {
                    $content .= call_user_func( array( $this, 'column_' . $column_name ), $item );
                } else {
                    $content .= $this->column_default( $item, $column_name );
                }
                if( $column_name !== 'error' && $column_name !== 'attachments') {
                    $content = htmlentities( $content );
                }
                $mailAppend .= $title . $content;
            }
        }

        return $mailAppend;
    }

    /**
     * Renders all components of the mail.
     * @since 1.6.0
     * @param array $item The current item.
     * @return string The mail as html
     */
    function render_mail_html( $item ) {
        $mailAppend = '';
        foreach ( $item as $column_name => $value ) {
            if ( array_key_exists( $column_name, $this->get_columns() ) && ! in_array( $column_name, $this->get_hidden_columns() ) ) {
                $display = $this->get_columns();
                $mailAppend .= "<span class=\"title\">{$display[$column_name]}: </span>";
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
            'resend'	=> 'Resend'
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

        if ( check_admin_referer( WPML_Email_Log_List::NONCE_LIST_TABLE, WPML_Email_Log_List::NONCE_LIST_TABLE . '_nonce' ) ) {
            $name = $this->_args['singular'];

            // Detect when a bulk action is being triggered.
            if ( 'delete' === $this->current_action() ) {
                foreach ( $_REQUEST[$name] as $item_id ) {
                    $mail = Mail::find_one( $item_id );
                    if ( false !== $mail ) {
                        $mail->delete();
                    }
                }
            } else if ( 'resend' == $this->current_action() ) {
                foreach ( $_REQUEST[$name] as $item_id ) {
                    $mail = Mail::find_one( $item_id );
                    if ( false !== $mail ) {
                        $this->resend_email( $mail );
                    }
                }
            }
        }
    }

    /**
     * Send logged email via wp_mail
     * @param Mail $mail the email object to resend
     * @since 1.8.0
     */
    function resend_email( $mail ) {
        $this->emailResender->resendMail( $mail );
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
     * @return array
     */
    function get_sortable_columns() {
        return array(
            // Description: column_name => array( 'display_name', true[asc] | false[desc] ).
            'mail_id'  		=> array( 'mail_id', false ),
            'timestamp' 	=> array( 'timestamp', true ),
            'host' 	        => array( 'host', true ),
            'receiver' 		=> array( 'receiver', true ),
            'subject' 		=> array( 'subject', true ),
            'headers' 		=> array( 'headers', true ),
            'plugin_version'=> array( 'plugin_version', true ),
        );
    }
}
