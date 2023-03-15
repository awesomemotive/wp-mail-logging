<?php

namespace No3x\WPML;

use No3x\WPML\Admin\SettingsTab;
use No3x\WPML\Model\Email_Log_Collection;
use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\Column\SanitizedColumnDecorator;
use No3x\WPML\Renderer\WPML_ColumnManager;

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
    /** @var WPML_ColumnManager $columnManager */
    private $columnManager;

    /**
     * Allowed actions.
     *
     * @since 1.11.0
     *
     * @var string[]
     */
    const ALLOWED_ACTIONS = [ 'delete', 'resend' ];

    /**
     * Nonce action for single log action.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const SINGLE_LOG_ACTION_NONCE = 'wp-mail-logging-single-log-action-nonce';

    /**
     * Current status of mails displayed.
     *
     * @since 1.11.0
     *
     * @var mixed
     */
    private $current_status = null;

    /**
     * Number of email logs by different statuses.
     *
     * @since 1.11.0
     *
     * @var array
     */
    private $statuses_counts = [];

    /**
     * Container for the notices.
     *
     * @since 1.11.0
     *
     * @var array
     */
    private $notices = [];

    /**
     * Initializes the List Table
     * @since 1.0
     * @param WPML_Email_Resender $emailResender
     */
    function __construct( $emailResender ) {
        $this->emailResender = $emailResender;
        $this->columnManager = new WPML_ColumnManager();
    }

    function addActionsAndFilters() {
        add_action( 'admin_init', array( $this, 'init') );
        add_action( 'current_screen', [ $this, 'process' ] );
        add_filter( 'wp_mail_logging_admin_logs_localize_strings', [ $this, 'add_localize_strings' ] );
    }

    function init() {
        global $status, $page, $hook_suffix;

        parent::__construct( array(
            'singular' => 'email', // singular name of the listed records
            'plural'   => 'emails',// plural name of the listed records
            'ajax'     => false, // does this table support ajax?
        ) );
    }

    /**
     * Process user actions.
     *
     * @since 1.11.0
     *
     * @param \WP_Screen $current_screen Current \WP_Screen object.
     *
     * @return void
     */
    public function process( $current_screen ) {

        global $wp_logging_list_page;

        if ( $current_screen->id !== $wp_logging_list_page ) {
            return;
        }

        $this->process_action();
        $this->add_notices();

        add_filter( 'removable_query_args', [ $this, 'removable_query_args' ] );
    }

    /**
     * Process action the admin initiated.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function process_action() {

        $current_action = $this->current_action();

        if ( empty( $current_action ) || ! in_array( $current_action, self::ALLOWED_ACTIONS ) ) {
            return;
        }

        $settings = SettingsTab::get_settings( SettingsTab::DEFAULT_SETTINGS );

        if ( ! current_user_can( $settings['can-see-submission-data'] ) ) {
            return;
        }

        $results = 0;

        if ( ! empty( $_GET[ self::SINGLE_LOG_ACTION_NONCE ] ) && check_admin_referer( self::SINGLE_LOG_ACTION_NONCE, self::SINGLE_LOG_ACTION_NONCE ) && ! empty( $_GET['email_log_id'] ) ) {

            // Number of logs we tried to perform action.
            $attempt_action_logs   = 1;
            $process_single_action = $this->process_single_log_action( absint( $_GET['email_log_id'] ), $current_action );

            if ( $process_single_action ) {
                $results = 1;
            }
        } else {
            $process_bulk_action = $this->process_bulk_action( true );
            $results             = ! empty( $process_bulk_action['success'] ) ? $process_bulk_action['success'] : 0;
            $attempt_action_logs = ! empty( $process_bulk_action['attempt'] ) ? $process_bulk_action['attempt'] : 0;
        }

        // Action redirect args.
        $action_redirect = [
            'delete' => 'deleted',
            'resend' => 'resent'
        ];

        wp_safe_redirect(
            add_query_arg(
                [
                    $action_redirect[ $current_action ] => absint( $results ),
                    'attempt_log_count' => absint( $attempt_action_logs ),
                ],
                remove_query_arg( [ 'action', 'action2', '_wpnonce', 'email_log_id', 'paged', '_wp_http_referer', 'wp-mail-logging-single-log-action-nonce' ] )
            )
        );
        exit;
    }

    /**
     * Add notices for performed action.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function add_notices() {

        $deleted           = filter_input( INPUT_GET, 'deleted', FILTER_VALIDATE_INT );
        $resent            = filter_input( INPUT_GET, 'resent', FILTER_VALIDATE_INT );
        $attempt_log_count = filter_input( INPUT_GET, 'attempt_log_count', FILTER_VALIDATE_INT );

        // If nothing was attempted to performed.
        if ( empty( $attempt_log_count ) ) {
            return;
        }

        if ( ! is_null( $deleted ) ) {

            if ( $deleted === 0 ) {
                $this->add_notice(
                    _n( 'Unable to delete email log.', 'Unable to delete email logs.', $attempt_log_count, 'wp-mail-logging' ),
                    'error'
                );

                return;
            }

            if ( $deleted === $attempt_log_count ) {

                if ( $deleted === 1 ) {
                    $notice_message = __( 'Email log was successfully deleted.', 'wp-mail-logging' );
                } else {
                    $notice_message = sprintf(
                        /* translators: %d: Number of email logs that was successfully deleted. */
                        _n(
                            '%d email log was successfully deleted.',
                            '%d email logs were successfully deleted!',
                            $deleted,
                            'wp-mail-logging'
                        ),
                        $deleted
                    );
                }

                $this->add_notice(
                    $notice_message,
                    'success'
                );

                return;
            }

            $this->add_notice(
                _n( 'One of the emails failed to be deleted.', 'Some emails failed to be deleted.', $attempt_log_count - $deleted, 'wp-mail-logging' ),
                'warning'
            );

            return;
        }

        if ( ! is_null( $resent ) ) {

            if ( $resent === 0 ) {
                $this->add_notice(
                    _n( 'Unable to add email to the sending queue.', 'Unable to add emails to the sending queue.', $attempt_log_count, 'wp-mail-logging' ),
                    'error'
                );

                return;
            }

            if ( $resent === $attempt_log_count ) {

                if ( $resent === 1 ) {
                    $notice_message = __( 'Email was added to the sending queue.', 'wp-mail-logging' );
                } else {
                    $notice_message = sprintf(
                        /* translators: %d: Number of email logs that was added to the sending queue. */
                        _n( '%d email was added to the sending queue.', '%d emails were added to the sending queue.', $resent, 'wp-mail-logging' ),
                        $resent
                    );
                }

                $this->add_notice(
                    $notice_message
                );

                return;
            }

            $this->add_notice(
               _n( 'One email failed to be added to the sending queue.', 'Some emails failed to be added to the sending queue.', $attempt_log_count - $resent, 'wp-mail-logging' ),
                'warning'
            );
        }

    }

    /**
     * Remove certain arguments from a query string that WordPress should always hide for users.
     *
     * @since 1.11.0
     *
     * @param array $removable_query_args An array of parameters to remove from the URL.
     *
     * @return array Extended/filtered array of parameters to remove from the URL.
     */
    public function removable_query_args( $removable_query_args ) {

        $removable_query_args[] = 'deleted';
        $removable_query_args[] = 'resent';
        $removable_query_args[] = 'attempt_log_count';
        $removable_query_args[] = 'email';
        $removable_query_args[] = 'wpml-list_table_nonce';

        return $removable_query_args;
    }

    /**
     * Add the single log action nonce and the current page URL in
     * the localized strings.
     *
     * @since 1.11.0
     *
     * @param array $data Data to be localized for JS usage.
     *
     * @return array
     */
    public function add_localize_strings( $data ) {

        $data[ 'single_log_action_nonce' ] = wp_create_nonce( self::SINGLE_LOG_ACTION_NONCE );
        $data[ 'single_log_action_key' ]   = self::SINGLE_LOG_ACTION_NONCE;
        $data[ 'admin_email_logs_url' ]    = $this->get_page_base_url();

        return $data;
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

    public function get_views() {
        $views = [];

        // Get base url.
        $email_log_page_url = $this->get_page_base_url();

        foreach ( Email_Log_Collection::get_statuses() as $status => $label ) {
            $views[ $status ] = sprintf(
                '<a href="%1$s" %2$s>%3$s <span class="count">(%4$d)</span></a>',
                esc_url( add_query_arg( 'status', $status, $email_log_page_url ) ),
                $this->get_current_status() == $status ? 'class="current"' : '',
                esc_html( $label ),
                absint( $this->statuses_counts[ $status ] )
            );
        }

        return $views;
    }

    /**
     * Get the base URL of the WP Mail Logging page.
     *
     * @since 1.11.0
     *
     * @return string
     */
    private function get_page_base_url() {

        return add_query_arg( 'page', 'wpml_plugin_log', WPML_Utils::get_admin_page_url() );
    }

    /**
     * Defines the available columns.
     *
     * @since 1.0
     * @since 1.11.0 Handle attachments column.
     *
     * @see WP_List_Table::get_columns()
     */
    function get_columns() {

        $settings = SettingsTab::get_settings([]);
        $columns  = array_merge(['cb' => '<input type="checkbox" />'], $this->columnManager->getColumns(), $this->get_actions_column() );

        if ( empty( $settings['display-host'] ) ) {
            unset( $columns['host'] );
        }

        if ( empty( $settings['display-attachments'] ) ) {
            unset( $columns['attachments'] );
        }

        return $columns;
    }

    /**
     * Column to display in mobile.
     *
     * @since 1.11.0
     *
     * @inerhitDoc
     */
    protected function get_primary_column_name() {

        return WPML_ColumnManager::COLUMN_TIMESTAMP;
    }

    /**
     * The actions column.
     *
     * @since 1.11.0
     *
     * @return string[]
     */
    private function get_actions_column() {
        return [ 'actions' => '' ];
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

        $allowed = array_keys( $this->get_sortable_columns() );

        return WPML_Utils::sanitize_expected_value(
            ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : null,
            $allowed,
            'mail_id'
        );
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

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $per_page = $this->get_items_per_page( 'per_page', 25 );

        $log_collection = new Email_Log_Collection( Mail::get_table(), Mail::get_searchable_fields() );
        $this->statuses_counts = $log_collection->get_statuses_count();

        $this->items = $log_collection->status( $this->get_current_status() )
            ->search( $search )
            ->sort_by( $this->sanitize_orderby() )
            ->order( $this->sanitize_order() )
            ->limit( $per_page )
            ->offset( ( $this->get_pagenum() - 1 ) * $per_page )
            ->find_list();

        $this->set_pagination_args( [
            'total_items' => $log_collection->count()->find_list(), // The total number of items.
            'per_page'    => $per_page, // Number of items per page.
        ] );
    }

    /**
     * Process action for a single log.
     *
     * @since 1.11.0
     *
     * @param int    $email_log_id Email Log ID in context.
     * @param string $action       Action to perform.
     *
     * @return bool
     */
    private function process_single_log_action( $email_log_id, $action ) {

        if ( empty( $email_log_id ) ) {
            return false;
        }

        $perform_action = $this->perform_action( $email_log_id, $action );

        if ( is_wp_error( $perform_action ) || ! $perform_action ) {
            return false;
        }

        return true;
    }

    /**
     * Perform an action to an email log.
     *
     * @since 1.11.0
     *
     * @param int    $email_log_id Email Log ID in context.
     * @param string $action       Action to perform to a mail.
     *
     * @return bool|\WP_Error Returns WP_Error when provided `$action` is invalid.
     *                        Otherwise returns a `bool` of whether the Mail is found or not.
     */
    private function perform_action( $email_log_id, $action ) {

        if ( ! in_array( $action, [ 'delete', 'resend' ], true ) ) {
            return new \WP_Error( 'wp-mail-logging-invalid-action', __( 'Invalid request!', 'wp-mail-logging' ) );
        }

        $mail = Mail::find_one( $email_log_id );

        if ( $mail === false ) {
            return false;
        }

        switch ( $action ) {
            case 'delete':
                return $mail->delete();

            case 'resend':
                $this->resend_email( $mail );
                break;

            default:
                return false;
        }

        return true;
    }

    /**
     * Processes bulk actions.
     *
     * @since 1.0
     * @since 1.11.0 Add first argument which is a bool whether to return a value or not.
     *
     * @param bool $return_info Whether to return a value or not. Default `false`.
     *
     * @return void|array
     */
    function process_bulk_action( $return_info = false ) {

        if ( false === $this->current_action() ) {
            return;
        }

        if (
            ! check_admin_referer( WPML_Email_Log_List::NONCE_LIST_TABLE, WPML_Email_Log_List::NONCE_LIST_TABLE . '_nonce' )
            || ! in_array( $this->current_action(), [ 'delete', 'resend' ], true )
        ) {
            return;
        }

        // Holds the number of Mail attempted to perform action with.
        $attempted_action_counter = 0;

        // Holds the number of successful actions performed.
        $successful_action_counter = 0;

        $current_action = $this->current_action();

        // Loop through each of the item.
        foreach ( $_REQUEST[ $this->_args['singular'] ] as $item_id ) {

            $attempted_action_counter++;

            $perform_action = $this->perform_action( $item_id, $current_action );

            if ( is_wp_error( $perform_action ) ) {
                continue;
            }

            if ( $perform_action ) {
                $successful_action_counter++;
            }
        }

        // For backward compatibility.
        if ( ! $return_info ) {
            return;
        }

        return [
            'success' => $successful_action_counter,
            'attempt' => $attempted_action_counter,
        ];
    }

    /**
     * Add notice to notices container.
     *
     * @since 1.11.0
     *
     * @param string $message Notice message.
     * @param string $type    Notice type. Default 'info'.
     *
     * @return void
     */
    private function add_notice( $message, $type = 'info' ) {

        $this->notices[] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Get the current status of email logs to display.
     *
     * @since 1.11.0
     *
     * @return mixed
     */
    private function get_current_status() {

        if ( ! is_null( $this->current_status ) ) {
            return $this->current_status;
        }

        // Get the current status.
        $current_status = filter_input( INPUT_GET, 'status', FILTER_SANITIZE_NUMBER_INT );
        if ( empty( $current_status ) || ! array_key_exists( $current_status, Email_Log_Collection::get_statuses() ) ) {
            $current_status = Email_Log_Collection::STATUS_ALL;
        }

        $this->current_status = $current_status;

        return $this->current_status;
    }

    /**
     * Renders the cell.
     * @since 1.0
     * @param array  $item The current item.
     * @param string $column_name The current column name.
     * @return string The cell content
     */
    function column_default( $item, $column_name ) {

        if ( $column_name === 'actions' ) {
            return $this->display_actions_icons( $item['mail_id'] );
        }

        return ( new SanitizedColumnDecorator($this->columnManager->getColumnRenderer($column_name)))->render($item, ColumnFormat::FULL);
    }

    /**
     * Display the action icons in the column.
     *
     * @since 1.11.0
     *
     * @param int $email_log_id Email Log ID.
     *
     * @return string
     */
    private function display_actions_icons( $email_log_id ) {

        $assets_url = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();

        return '<div class="wp-mail-logging-action-column" data-mail-id="' . esc_attr( $email_log_id )  . '">
            <button class="wp-mail-logging-action-item" data-action="view">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 10C9.10457 10 10 9.10457 10 8C10 6.89543 9.10457 6 8 6C6.89543 6 6 6.89543 6 8C6 9.10457 6.89543 10 8 10Z" fill="#50575E" fill-opacity="0.8"/>
                    <path d="M15.47 7.83C14.882 6.30882 13.861 4.99331 12.5334 4.04604C11.2058 3.09878 9.62977 2.56129 8.00003 2.5C6.37029 2.56129 4.79423 3.09878 3.46663 4.04604C2.13904 4.99331 1.11811 6.30882 0.530031 7.83C0.490315 7.93985 0.490315 8.06015 0.530031 8.17C1.11811 9.69118 2.13904 11.0067 3.46663 11.954C4.79423 12.9012 6.37029 13.4387 8.00003 13.5C9.62977 13.4387 11.2058 12.9012 12.5334 11.954C13.861 11.0067 14.882 9.69118 15.47 8.17C15.5098 8.06015 15.5098 7.93985 15.47 7.83ZM8.00003 11.25C7.35724 11.25 6.72889 11.0594 6.19443 10.7023C5.65997 10.3452 5.24341 9.83758 4.99742 9.24372C4.75144 8.64986 4.68708 7.99639 4.81248 7.36596C4.93788 6.73552 5.24741 6.15642 5.70193 5.7019C6.15646 5.24738 6.73555 4.93785 7.36599 4.81245C7.99643 4.68705 8.64989 4.75141 9.24375 4.99739C9.83761 5.24338 10.3452 5.65994 10.7023 6.1944C11.0594 6.72886 11.25 7.35721 11.25 8C11.2487 8.86155 10.9059 9.68743 10.2967 10.2966C9.68746 10.9058 8.86158 11.2487 8.00003 11.25Z" fill="currentColor"/>
                </svg>
            </button>
            <button class="wp-mail-logging-action-item" data-action="resend">
                 <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.39998 3.3079C2.39998 3.06708 2.49832 2.83613 2.67338 2.66585C2.84844 2.49557 3.08586 2.3999 3.33343 2.3999C3.581 2.3999 3.81843 2.49557 3.99348 2.66585C4.16854 2.83613 4.26688 3.06708 4.26688 3.3079V4.08998C5.18891 3.28835 6.35776 2.8061 7.59112 2.71844C8.82447 2.63078 10.053 2.94265 11.085 3.6054C12.117 4.26815 12.8945 5.24452 13.2962 6.38219C13.698 7.51987 13.7013 8.75488 13.3057 9.8946C12.9101 11.0343 12.1379 12.0146 11.1095 12.6826C10.0811 13.3506 8.85425 13.6688 7.62044 13.5874C6.38663 13.5061 5.21519 13.0298 4.28885 12.2329C3.36251 11.436 2.73337 10.3632 2.49954 9.18202C2.39002 8.61906 2.8779 8.15054 3.4666 8.15054C3.90844 8.15054 4.25817 8.50284 4.35152 8.92294C4.52107 9.68189 4.93619 10.368 5.53598 10.8807C6.13577 11.3933 6.88867 11.7055 7.68423 11.7715C8.47978 11.8374 9.27613 11.6537 9.95646 11.2471C10.6368 10.8406 11.1653 10.2327 11.4644 9.51259C11.7636 8.7925 11.8176 7.99811 11.6186 7.24595C11.4196 6.49379 10.9781 5.82345 10.3588 5.33327C9.73944 4.84308 8.97493 4.55884 8.17737 4.52225C7.37982 4.48566 6.5912 4.69863 5.92719 5.12994H6.44495C6.69251 5.12994 6.92994 5.2256 7.105 5.39589C7.28006 5.56617 7.3784 5.79712 7.3784 6.03794C7.3784 6.27875 7.28006 6.5097 7.105 6.67999C6.92994 6.85027 6.69251 6.94593 6.44495 6.94593H3.33343C3.08586 6.94593 2.84844 6.85027 2.67338 6.67999C2.49832 6.5097 2.39998 6.27875 2.39998 6.03794V3.3079Z" fill="currentColor"/>
                </svg>
            </button>
            <button class="wp-mail-logging-action-item"  data-action="delete">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 4.66683H3.33333V13.3335C3.33333 13.6871 3.47381 14.0263 3.72386 14.2763C3.97391 14.5264 4.31304 14.6668 4.66667 14.6668H11.3333C11.687 14.6668 12.0261 14.5264 12.2761 14.2763C12.5262 14.0263 12.6667 13.6871 12.6667 13.3335V4.66683H4ZM6.66667 12.6668H5.33333V6.66683H6.66667V12.6668ZM10.6667 12.6668H9.33333V6.66683H10.6667V12.6668ZM11.0787 2.66683L10 1.3335H6L4.92133 2.66683H2V4.00016H14V2.66683H11.0787Z" fill="currentColor"/>
                </svg>
            </button>
        </div>';
    }

    /**
     * Defines available bulk actions.
     * @since 1.0
     * @see WP_List_Table::get_bulk_actions()
     */
    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete',
            'resend'    => 'Resend'
        );
        return $actions;
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
            'mail_id'       => array( 'mail_id', false ),
            'timestamp'     => array( 'timestamp', true ),
            'host'          => array( 'host', true ),
            'receiver'      => array( 'receiver', true ),
            'subject'       => array( 'subject', true ),
            'headers'       => array( 'headers', true ),
            'plugin_version'=> array( 'plugin_version', true ),
        );
    }

    /**
     * Display the notices.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function display_notices() {

        foreach( $this->notices as $notice ) {
            ?>
            <div class="notice wp-mail-logging-notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible inline">
                <p><?php echo esc_html( $notice['message'] ); ?></p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-mail-logging' ); ?></span>
                </button>
            </div>
            <?php
        }
    }
}
