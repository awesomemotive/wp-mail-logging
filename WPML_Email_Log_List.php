<?php

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Model\WPML_Mail;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

require_once( ABSPATH . 'wp-admin/includes/screen.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( plugin_dir_path( __FILE__ ) . 'inc/class-wp-list-table.php' );
}

/**
 * Renders the mails in a table list.
 * @author No3x
 * @since 1.0
 */
class WPML_Email_Log_List extends \WP_List_Table {

    const NONCE_LIST_TABLE = 'wpml-list_table';
    private $supported_formats = array();
    /**
     * Initializes the List Table
     * @since 1.0
     */
    function __construct( $supported_formats = array() ) {
        $this->supported_formats = $supported_formats;
    }

    function addActionsAndFilters() {
        add_action( 'admin_init', array( $this, 'init') );
        add_filter( WPML_Plugin::HOOK_LOGGING_SUPPORTED_FORMATS, function() {
            return $this->supported_formats;
        } );
        add_action( 'wp_ajax_wpml_email_get', __CLASS__ . '::ajax_wpml_email_get' );
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
            case 'host':
            case 'subject':
            case 'message':
            case 'headers':
            case 'attachments':
            case 'error':
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
     * @since 1.5.1
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
     * @return string
     */
    function column_message( $item ) {
        if ( empty( $item['message'] ) ) {
            return '';
        }
        $content = $item['mail_id'];
        $message = '<a class="wp-mail-logging-view-message button button-secondary" href="#" data-mail-id="' . esc_attr( $content )  . '">View</a>';
        return $message;
    }

    /**
     * Renders the timestamp column.
     * @since 1.5.0
     * @param array $item The current item.
     * @return string
     */
    function column_timestamp( $item ) {
        return date_i18n( apply_filters( 'wpml_get_date_time_format', '' ), strtotime( $item['timestamp'] ) );
    }

    /**
     * Renders the attachment column in compbat mode for mails prior 1.6.0.
     * @since 1.6.0
     * @param array $item The current item.
     * @return string The attachment column.
     */
    function column_attachments_compat_152( $item ) {
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
                    $message = sprintf( __( 'Attachment %s is not present', 'wp-mail-logging' ), $filename );
                    $attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
                }
            }
        }
        return $attachment_append;
    }

    /**
     * Renders the attachment column.
     * @since 1.3
     * @param array $item The current item.
     * @return string The attachment column.
     */
    function column_attachments( $item ) {

        if ( version_compare( trim( $item ['plugin_version'] ), '1.6.0', '<' ) ) {
            return $this->column_attachments_compat_152( $item );
        }

        $attachment_append = '';
        $attachments = explode( ',\n', $item['attachments'] );
        $attachments = is_array( $attachments ) ? $attachments : array( $attachments );
        foreach ( $attachments as $attachment ) {
            // $attachment can be an empty string ''.
            if ( ! empty( $attachment ) ) {
                $filename = basename( $attachment );
                $basename = '/uploads';
                $attachment_path = WP_CONTENT_DIR . $basename . $attachment;
                $attachment_url = WP_CONTENT_URL . $basename . $attachment;

                if ( is_file( $attachment_path ) ) {
                    $attachment_append .= '<a href="' . $attachment_url . '" title="' . $filename . '">' . WPML_Utils::generate_attachment_icon( $attachment_path ) . '</a> ';
                } else {
                    $message = sprintf( __( 'Attachment %s is not present', 'wp-mail-logging' ), $filename );
                    $attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
                }
            }
        }
        return $attachment_append;
    }

    /**
     * Renders the error column.
     * @since 1.8.0
     * @param $item
     * @return string
     */
    function column_error($item ) {
        $error = $item['error'];
        if( empty($error)) return "";
        $errorMessage = is_array($error) ? join(',', $error) : $error;
        return "<i class='fa fa-exclamation-circle' title='{$errorMessage}' aria-hidden='true'></i>";
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
                $title = "<span class=\"title\">{$display[$key]}: </span>";
                $content = '';
                if ( 'message' !== $column_name  && method_exists( $this, 'column_' . $column_name ) ) {
                    if( 'error' === $column_name || 'attachments' === $column_name ) {
                        // don't render with icons and stuff, just plain
                        $content .= is_array($item[$column_name]) ? join("\n", $item[$column_name]) : $item[$column_name];
                    } else {
                        $content .= call_user_func( array( $this, 'column_' . $column_name ), $item );
                    }
                } else {
                    $content .= $this->column_default( $item, $column_name );
                }
                $mailAppend .= $title . htmlentities( $content );
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
     * @param WPML_Mail $mail the email object to resend
     * @since 1.8.0
     */
    function resend_email( $mail ) {
        wp_mail( $mail->get_receiver(), $mail->get_subject(), $mail->get_message(), $mail->get_headers(), $mail->get_attachments() ) ;
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
            'message' 		=> array( 'message', true ),
            'headers' 		=> array( 'headers', true ),
            'attachments' 	=> array( 'attachments', true ),
            'plugin_version'=> array( 'plugin_version', true ),
        );
    }

    /**
     * Ajax function to retrieve rendered mail in certain format.
     * @since 1.6.0
     */
    public static function ajax_wpml_email_get() {
        $formats = is_array( $additional = apply_filters( WPML_Plugin::HOOK_LOGGING_SUPPORTED_FORMATS, array() ) ) ? $additional : array();

        check_ajax_referer( 'wpml-modal-show', 'ajax_nonce', true );

        if( ! isset( $_POST['id'] ) )
            wp_die( "huh?" );
        $id = intval( $_POST['id'] );

        $format_requested = isset( $_POST['format'] ) ? $_POST['format'] : 'html';
        if ( ! in_array( $format_requested, $formats ) )  {
            echo "Unsupported Format. Using html as fallback.";
            $format_requested = WPML_Utils::sanitize_expected_value($format_requested, $formats, 'html');
        }
        $mail = Mail::find_one( $id );
        /* @var $instance WPML_Email_Log_List */
        $instance = WPML_Init::getInstance()->getService( 'emailLogList' );
        $mailAppend = '';
        switch( $format_requested ) {
            case 'html': {
                $mailAppend .= $instance->render_mail_html( $mail->to_array() );
                break;
            }
            case 'raw': {
                $mailAppend .= $instance->render_mail( $mail->to_array() );
                break;
            }
            case 'json': {
                if( stristr( str_replace(' ', '', $mail->get_headers()),  "Content-Type:text/html")) {
                    // Fallback to raw in case it is a html mail
                    $mailAppend .= sprintf("<span class='info'>%s</span>", __("Fallback to raw format because html is not convertible to json.", 'wp-mail-logging' ) );
                    $mailAppend .= $instance->render_mail( $mail->to_array() );
                } else {
                    $mailAppend .= "<pre>" . json_encode( $mail->to_array(), JSON_PRETTY_PRINT ) . "</pre>";
                }
                break;
            }
            default:
                $mailAppend .= apply_filters( WPML_Plugin::HOOK_LOGGING_FORMAT_CONTENT . "_{$format_requested}", $mail->to_array() );
                break;
        }
        echo $mailAppend;
        wp_die(); // this is required to terminate immediately and return a proper response
    }
}
