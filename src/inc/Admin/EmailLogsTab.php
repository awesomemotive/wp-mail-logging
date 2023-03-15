<?php

namespace No3x\WPML\Admin;

use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\WPML_Email_Log_List;
use No3x\WPML\WPML_Init;
use No3x\WPML\WPML_ProductEducation;
use No3x\WPML\WPML_Utils;

class EmailLogsTab {

    /**
     * Top product education banner ID.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const BANNER_TOP_ID = 'email-logs-top';

    /**
     * Bottom product education banner ID.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const BANNER_BOTTOM_ID = 'email-logs-bottom';

    /**
     * Nonce used for requesting single email content preview.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const SINGLE_EMAIL_CONTENT_PREVIEW_MODE_NONCE = 'wp-mail-logging-single-email-preview';

    /**
     * Only instance of this object.
     *
     * @since 1.11.0
     *
     * @var EmailLogsTab
     */
    private static $instance = null;

    /**
     * Array containing the current logs displayed with errors.
     *
     * @since 1.11.0
     *
     * @var null|array
     */
    private $current_logs_with_errors = null;

    /**
     * Email Log List object.
     *
     * @since 1.11.0
     *
     * @var WPML_Email_Log_List
     */
    private $emailLogList;

    /**
     * Get the only instance of this object.
     *
     * @since 1.11.0
     *
     * @return EmailLogsTab
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.11.0
     */
    private function __construct() {
    }

    /**
     * Hooks that are fired earlier.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function hooks() {

        add_action( 'admin_init', [ $this, 'process_email_content_preview' ] );
    }

    /**
     * Render an Email log HTML content.
     *
     * This is used to preview an HTML content in the Single Log Modal.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function process_email_content_preview() {

        if ( empty( $_GET['page'] ) || $_GET['page'] !== WPML_Utils::ADMIN_PAGE_SLUG
            || empty( $_GET['email_log_id'] ) || empty( $_GET[ self::SINGLE_EMAIL_CONTENT_PREVIEW_MODE_NONCE ] )
            || empty( $_GET['mode'] ) || $_GET['mode'] !== 'iframe_preview' ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_key( $_GET[ self::SINGLE_EMAIL_CONTENT_PREVIEW_MODE_NONCE ] ), self::SINGLE_EMAIL_CONTENT_PREVIEW_MODE_NONCE ) ) {
            return;
        }

        // Get the mail log.
        $mail = Mail::find_one( absint( $_GET['email_log_id'] ) );

        if ( ! $mail ) {
            return;
        }

        echo $mail->get_message();
        exit;
    }

    /**
     * Only add hooks here that are invoked after `current_screen` action hook.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function screen_hooks() {

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_mail_logging_email_logs_tab_display_before', [ $this, 'product_education_email_failure' ] );
        add_action( 'wp_mail_logging_email_logs_tab_display_after', [ $this, 'product_education_wp_mail_smtp' ] );
        add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
        add_action( 'wp_mail_logging_admin_tab_content', [ $this, 'display_tab_content' ] );
    }

    /**
     * Enqueue scripts in Email Logs tab.
     *
     * @since 1.11.0
     *
     * @return void
     *
     * @throws \Exception
     */
    public function enqueue_scripts() {

        $assets_url  = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
        $plugin_meta = WPML_Init::getInstance()->getService( 'plugin-meta' );

        if ( empty( $plugin_meta['version'] ) ) {
            return;
        }

        $min = '';

        if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
            $min = '.min';
        }

        wp_enqueue_script(
            'wp-mail-logging-admin-logs',
            $assets_url . "/js/wp-mail-logging-admin-logs{$min}.js",
            [ 'jquery' ],
            $plugin_meta['version']
        );

        /**
         * Filters the strings to be localized and used in JS.
         *
         * @param string $data Data to be localized.
         *
         * @since 1.11.0
         */
        $filtered_localized_strings = apply_filters(
            'wp_mail_logging_admin_logs_localize_strings',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ]
        );

        wp_localize_script(
            'wp-mail-logging-admin-logs',
            'wp_mail_logging_admin_logs',
            $filtered_localized_strings
        );

        // Enqueue Lity.
        wp_enqueue_script(
            'wp-mail-logging-admin-lity',
            $plugin_meta['uri'] . "lib/lity/lity.min.js",
            [],
            '2.4.1',
            true
        );

        wp_enqueue_style(
            'wp-mail-logging-admin-lity',
            $plugin_meta['uri'] . "lib/lity/lity.min.css",
            [],
            '2.4.1'
        );
    }

    /**
     * Display product education for email failures.
     *
     * Only display this product education if the current page has email logs with errors.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function product_education_email_failure() {

        if ( empty( $this->get_current_logs_with_errors() ) ) {
            return;
        }

        /** @var WPML_ProductEducation $productEducation */
        $productEducation = WPML_Init::getInstance()->getService( 'productEducation' );

        if ( $productEducation->is_banner_dismissed( self::BANNER_TOP_ID ) ) {
            return;
        }

        $content = '<div>';
        $content .= '<p>' . esc_html__( "To solve email delivery issues, install WP Mail SMTP (free) - trusted by over 3,000,00 sites!", 'wp-mail-logging' ) . '</p>';
        $content .= '<p>' . esc_html__( "Use the one-click install and setup wizard to fix your emails in minutes.", 'wp-mail-logging' ) . '</p>';
        $content .= '</div>';

        WPML_ProductEducation::create_banner(
            self::BANNER_TOP_ID,
            esc_html__( 'Heads up! WP Mail Logging has detected a problem sending emails.', 'wp-mail-logging' ),
            $content,
            [
                'url'   => SMTPTab::get_url(),
                'label' => esc_html__( 'Install WP Mail SMTP', 'wp-mail-logging' ),
            ]
        );
    }

    /**
     * Returns an array containing the current logs in the page with errors.
     *
     * @since 1.11.0
     *
     * @return array
     */
    private function get_current_logs_with_errors() {

        if ( ! is_null( $this->current_logs_with_errors ) ) {
            return $this->current_logs_with_errors;
        }

        if ( ! $this->emailLogList->has_items() ) {
            $this->current_logs_with_errors = [];
            return [];
        }

        $this->current_logs_with_errors = array_filter( $this->emailLogList->items, function( $log ) {
            return ! empty( $log['error'] );
        } );

        return $this->current_logs_with_errors;
    }

    /**
     * Display product education about WP Mail SMTP if there's no logs with errors in the page.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function product_education_wp_mail_smtp() {

        /** @var WPML_ProductEducation $productEducation */
        $productEducation = WPML_Init::getInstance()->getService( 'productEducation' );

        if ( $productEducation->is_banner_dismissed( self::BANNER_BOTTOM_ID ) ) {
            return;
        }

        if ( ! empty( $this->get_current_logs_with_errors() ) && ! $productEducation->is_banner_dismissed( self::BANNER_TOP_ID ) ) {
            return;
        }

        $features = [
            [
                'filename' => 'archive',
                'label'    => __( 'Email Logs', 'wp-mail-logging' ),
                'list'     => [
                    __( 'See delivery status', 'wp-mail-logging' ),
                    __( 'Resend emails', 'wp-mail-logging' ),
                    __( 'View original email content', 'wp-mail-logging' ),
                ],
            ],
            [
                'filename' => 'single',
                'label'    => __( 'Individual Log', 'wp-mail-logging' ),
                'list'     => [
                    __( 'Review technical details', 'wp-mail-logging' ),
                    __( 'Track open and click data', 'wp-mail-logging' ),
                    __( 'Download sent attachments', 'wp-mail-logging' ),
                ],
            ],
            [
                'filename' => 'reports',
                'label'    => __( 'Email Reports', 'wp-mail-logging' ),
                'list'     => [
                    __( 'Generate deliverability charts', 'wp-mail-logging' ),
                    __( 'Review open & click statistics', 'wp-mail-logging' ),
                    __( 'Get weekly email summary', 'wp-mail-logging' ),
                ],
            ],
        ];

        ob_start();
        ?>
        <p>
            <?php
            echo wp_kses(
                sprintf(
                    /* translators: 1: URL to WP Mail SMTP pricing page 2: URL to WP Forms pricing page */
                    __( 'Want more from your email logs? <strong><a target="_blank" href="%1$s">WP Mail SMTP Pro</a></strong> offers advanced email logging, failed email alerts, backup connections, email reports, email tracking, and much more!', 'wp-mail-logging' ),
                    esc_url( WPML_Utils::get_utm_url( 'https://wpmailsmtp.com/', 'general' ) )
                ),
                [
                    'a'      => [
                        'target' => [],
                        'href'   => [],
                    ],
                    'strong' => [],
                ]
            )
            ?>
        </p>
        <p>
            <?php esc_html_e( "We know you'll love the powerful features in WP Mail SMTP. It's used by over 3,000,000 websites.", 'wp-mail-logging' ); ?>
        </p>

        <div class="wp-mail-logging-product-education-images-row">

            <?php
            $assets_url = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
            foreach ( $features as $feature ) {
                ?>
                <div class="wp-mail-logging-product-education-images-row-image">
                    <a href="<?php echo esc_url( $assets_url . "/images/prod-edu/{$feature['filename']}.png" ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $feature['label'] ); ?>">
                        <img src="<?php echo esc_url( $assets_url . "/images/prod-edu/{$feature['filename']}-thumbnail.png" ); ?>" alt="<?php esc_attr( $feature['label'] ); ?>">
                    </a>
                    <span><?php echo esc_html( $feature['label'] ) ?></span>

                    <ul>
                        <?php
                        foreach ( $feature['list'] as $list ) {
                            printf( '<li>%s</li>', esc_html( $list ) );
                        }
                        ?>
                    </ul>
                </div>
            <?php
            }
            ?>
        </div>
        <?php
        $content = ob_get_clean();

        WPML_ProductEducation::create_banner(
            self::BANNER_BOTTOM_ID,
            __( 'Take Your Email Logs to the Next level', 'wp-mail-logging' ),
            $content,
            [
                'url'    => WPML_Utils::get_utm_url( 'https://wpmailsmtp.com/pricing/', 'Get WP Mail SMTP Pro' ),
                'label'  => __( 'Get WP Mail SMTP Pro', 'wp-mail-logging' ),
                'target' => '_blank',
            ]
        );
    }

    /**
     * Display the Email Logs tab content.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function display_tab_content() {

        // Admin notices display after the first heading tag.
        echo '<h1 class="wp-mail-logging-hide">' . esc_html__( 'Email Logs', 'wp-mail-logging' ) . '</h1>';

        $this->emailLogList = WPML_Init::getInstance()->getService('emailLogList');
        $search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;
        $this->emailLogList->prepare_items( $search );

        /**
         * Fires before displaying the email logs content.
         *
         * @since 1.11.0
         *
         * @param WPML_Email_Log_List $emailLogList Email Log List object.
         */
        do_action( 'wp_mail_logging_email_logs_tab_display_before', $this->emailLogList );

        $this->emailLogList->display_notices();

        $this->single_log_modal();
        $this->display_table();

        /**
         * Fires after displaying the email logs content.
         *
         * @since 1.11.0
         *
         * @param WPML_Email_Log_List $emailLogList Email Log List object.
         */
        do_action( 'wp_mail_logging_email_logs_tab_display_after', $this->emailLogList );
    }

    /**
     * Modal template for showing single log details.
     *
     * @since 1.11.0
     *
     * @return void
     */
    private function single_log_modal() {

        global $wp_version;
        ?>
        <div id="wp-mail-logging-modal-wrap">
            <div id="wp-mail-logging-modal-backdrop"></div>
                <div id="wp-mail-logging-modal-content-wrap">
                    <div id="wp-mail-logging-modal-content">
                        <div id="wp-mail-logging-modal-content-header">

                            <div id="wp-mail-logging-modal-content-header-title">
                                <?php _e( 'Message', 'wp-mail-logging' ); ?>
                            </div>

                            <a id="wp-mail-logging-modal-content-header-close" class="wp-mail-logging-modal-close" href="#" title="Close">
                                <?php if ( $wp_version >= 3.8 ): ?>
                                    <div class="dashicons dashicons-no"></div>
                                <?php else: ?>
                                    <span class="wp-mail-logging-modal-content-header-compat-close">X</span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div id="wp-mail-logging-modal-content-body">
                            <div id="wp-mail-logging-modal-content-header-format-switch">
                                <ul>
                                    <?php
                                    foreach( WPML_Init::getInstance()->getService('supported-mail-renderer-formats') as $key => $format ) {
                                        $active_class = WPML_Init::getInstance()->getService( 'plugin' )->getSetting( 'preferred-mail-format' ) === $format ? 'wp-mail-logging-active-format' : '';
                                        ?>
                                        <li><a id="wp-mail-logging-modal-format-<?php echo esc_attr( $format ); ?>" class="wp-mail-logging-modal-format <?php echo esc_attr( $active_class ); ?>" href="#" data-format="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( $format ); ?></a></li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div id="wp-mail-logging-modal-content-body-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }

    /**
     * Display the email logs table.
     *
     * @since 1.11.0
     *
     * @return void
     */
    private function display_table() {
        ?>
        <form id="email-list" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
            <?php
            wp_nonce_field( WPML_Email_Log_List::NONCE_LIST_TABLE, WPML_Email_Log_List::NONCE_LIST_TABLE . '_nonce' );
            $this->emailLogList->search_box( __( 'Search', 'wp-mail-logging' ), 's' );
            $this->emailLogList->views();
            $this->emailLogList->display();
            ?>
        </form>
        <?php
    }

    /**
     * Add admin body class for WP Mail Logging logs page.
     *
     * @since 1.11.0
     *
     * @param $classes Space-separated list of CSS classes.
     *
     * @return string
     */
    public function add_admin_body_class( $classes ) {

        global $wp_logging_list_page;

        $current_screen = get_current_screen();

        if ( empty( $current_screen ) || ! is_a( $current_screen, 'WP_Screen' ) || $current_screen->id !== $wp_logging_list_page ) {
            return $classes;
        }

        return $classes . ' wp-mail-logging-admin-page';
    }
}
