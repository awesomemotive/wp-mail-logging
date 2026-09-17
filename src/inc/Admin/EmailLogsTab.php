<?php

namespace No3x\WPML\Admin;

use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Renderer\RemoteContentDetector;
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
     * Sandbox tokens applied to the email preview.
     *
     * Popups are permitted so links can open in a new tab, and that tab
     * escapes the sandbox so the destination site works normally. Nothing
     * else is granted: no script, no forms, no admin origin, and no ability
     * to navigate the admin window. The Content Security Policy repeats
     * these tokens, so both must be changed together.
     *
     * @since 1.17.0
     *
     * @var string
     */
    const PREVIEW_SANDBOX_TOKENS = 'allow-popups allow-popups-to-escape-sandbox';

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

        if ( ! WPML_Utils::can_current_user_access_wp_mail_logging_submissions() ) {
            return;
        }

        // Get the mail log.
        $mail = Mail::find_one( absint( $_GET['email_log_id'] ) );

        if ( ! $mail ) {
            return;
        }

        $mail_id = absint( $_GET['email_log_id'] );

        // `get_settings()` does not merge defaults, so an install upgraded from
        // an earlier version has no such key. empty() neither warns nor treats
        // a missing key as enabled, so the upgrade failure mode is the safe one.
        $settings       = SettingsTab::get_settings( SettingsTab::DEFAULT_SETTINGS );
        $remote_allowed = ! empty( $settings['load-remote-images'] ) || ! empty( $_GET['load_remote'] );

        $headers_sent = headers_sent();

        if ( ! $headers_sent ) {
            header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'Referrer-Policy: no-referrer' );
            header( 'Content-Security-Policy: ' . $this->get_csp_header_value( $remote_allowed, $mail_id ) );
        }

        // Sent as a prelude rather than a full document wrapper, so emails that
        // style `body` keep rendering as they do today. The base element is what
        // sends links with no target of their own out of the frame.
        echo '<meta name="referrer" content="no-referrer">' . "\n";
        echo '<base target="_blank">' . "\n";

        // Another plugin flushing output before `admin_init` costs this response every
        // one of its headers. The meta policy recovers what meta form can carry.
        if ( $headers_sent ) {
            printf(
                '<meta http-equiv="Content-Security-Policy" content="%s">' . "\n",
                esc_attr( $this->get_csp_meta_value( $remote_allowed, $mail_id ) )
            );
        }

        $preview = $this->get_html_preview_message( $mail->get_message() );

        // With no real CSP the meta policy above is the only thing blocking remote
        // content, and it is ignored outright when the flushed output was markup
        // rather than whitespace. Take the references out of the markup instead, so
        // the opt-in still means something on a response that lost its headers.
        if ( $headers_sent && ! $remote_allowed ) {
            $preview = RemoteContentDetector::strip_remote_content( $preview );
        }

        echo $preview;
        exit;
    }

    /**
     * Get the message to be rendered in the preview.
     *
     * @since 1.11.1
     * @since 1.15.0 Added filterable `$allowed_html` and `$allowed_protocols` to `wp_kses()`.
     * @since 1.17.0 `$allowed_html` now has `a.target` and `a.rel` removed before it reaches
     *               the `wp_mail_logging_allowed_html_email_html_preview` filter.
     *
     * @param string $message Email log message.
     *
     * @return string
     */
    private function get_html_preview_message( $message ) {

        // Strip <xml> and comment tags.
        $message = preg_replace( '/<xml\b[^>]*>(.*?)<\/xml>/is', '', $message );
        $message = preg_replace( '/<!--(.*?)-->/', '', $message );

        $allowed_html              = wp_kses_allowed_html( 'post' );
        $allowed_html['style'][''] = true;

        // Removed before the filter below runs, so a filter that rebuilds $allowed_html
        // from wp_kses_allowed_html( 'post' ) (rather than mutating what it received) will
        // re-add `a.target`/`a.rel`. That is harmless here: the iframe sandbox still blocks
        // top-level navigation, but it is worth writing down since it is easy to miss.
        // `area` carries the same `target` in WordPress's post allow-list, so an image
        // map would otherwise keep the route that unsetting `a.target` closes.
        unset(
            $allowed_html['a']['target'],
            $allowed_html['a']['rel'],
            $allowed_html['area']['target'],
            $allowed_html['area']['rel']
        );

         /**
         * Filters the allowed HTML in the email HTML preview.
         *
         * @since 1.15.0
         *
         * @param string[] $allowed_html Array of allowed HTML.
         * @param string   $message      Email message.
         */
        $allowed_html = apply_filters(
            'wp_mail_logging_allowed_html_email_html_preview',
            $allowed_html,
            $message
        );

        /**
         * Filters the allowed protocols in the email HTML preview.
         *
         * @since 1.15.0
         *
         * @param string[] $allowed_protocols Array of allowed protocols.
         * @param string   $message           Email message.
         */
        $allowed_protocols = apply_filters(
            'wp_mail_logging_allowed_protocols_email_html_preview',
            wp_allowed_protocols(),
            $message
        );

        return wp_kses( $message, $allowed_html, $allowed_protocols );
    }

    /**
     * Build the Content Security Policy for the email preview response.
     *
     * @since 1.17.0
     *
     * @param bool $remote_allowed Whether remote images and fonts may load.
     * @param int  $mail_id        Email log ID being previewed.
     *
     * @return string
     */
    private function get_csp_header_value( $remote_allowed, $mail_id ) {

        return self::join_csp_directives( $this->get_csp_directives( $remote_allowed, $mail_id ) );
    }

    /**
     * Build the Content Security Policy for the `<meta http-equiv>` fallback.
     *
     * Used when `headers_sent()` is already true and the real header can no longer be
     * sent. `sandbox` and `frame-ancestors` are dropped because browsers ignore both in
     * meta form; the iframe's `sandbox` attribute supplies the former either way.
     *
     * This is best effort on its own. A meta policy only applies while the parser is
     * still in `<head>`, so whatever was flushed first decides whether it counts:
     * whitespace keeps it in head, but real markup opens `<body>` and the policy is
     * ignored. `strip_remote_content()` is what actually holds in that case.
     *
     * @since {VERSION}
     * @access private
     *
     * @param bool $remote_allowed Whether remote images and fonts may load.
     * @param int  $mail_id        Email log ID being previewed.
     *
     * @return string
     */
    private function get_csp_meta_value( $remote_allowed, $mail_id ) {

        $directives = $this->get_csp_directives( $remote_allowed, $mail_id );

        unset( $directives['sandbox'], $directives['frame-ancestors'] );

        return self::join_csp_directives( $directives );
    }

    /**
     * Join a directive map into a policy string.
     *
     * @since {VERSION}
     * @access private
     *
     * @param array $directives Map of directive name to value.
     *
     * @return string
     */
    private static function join_csp_directives( $directives ) {

        $parts = [];

        foreach ( $directives as $directive => $value ) {
            $parts[] = $value === '' ? $directive : $directive . ' ' . $value;
        }

        return implode( '; ', $parts );
    }

    /**
     * Build the Content Security Policy directives for the email preview response.
     *
     * @since {VERSION}
     * @access private
     *
     * @param bool $remote_allowed Whether remote images and fonts may load.
     * @param int  $mail_id        Email log ID being previewed.
     *
     * @return array Map of directive name to value.
     */
    private function get_csp_directives( $remote_allowed, $mail_id ) {

        $resource = $remote_allowed ? 'https: data:' : 'data:';

        $directives = [
            'default-src'     => "'none'",
            'img-src'         => $resource,
            'font-src'        => $resource,
            'style-src'       => "'unsafe-inline'",
            'base-uri'        => "'none'",
            'form-action'     => "'none'",
            'frame-ancestors' => "'self'",
            'sandbox'         => self::PREVIEW_SANDBOX_TOKENS,
        ];

        /**
         * Filters the Content Security Policy directives for the email HTML preview.
         *
         * This is the only supported way to change the `sandbox` directive's value.
         * It must be kept in sync with the `PREVIEW_SANDBOX_TOKENS` constant, which
         * supplies the same tokens to the iframe's `sandbox` attribute -- browsers take
         * the intersection of the header and attribute, so narrowing one without the
         * other silently breaks the preview (e.g. a filtered `sandbox allow-scripts`
         * loses `allow-popups`, and every link in the preview stops working with no
         * console error).
         *
         * @since 1.17.0
         *
         * @param array $directives     Map of directive name to value.
         * @param bool  $remote_allowed Whether remote images and fonts may load.
         * @param int   $mail_id        Email log ID being previewed.
         *
         * @return array A filter must return a non-empty array of directives; anything else
         *               (an empty array, or a non-array value) leaves the default directives in place.
         */
        $filtered_directives = apply_filters(
            'wp_mail_logging_csp_email_html_preview',
            $directives,
            $remote_allowed,
            $mail_id
        );

        // A filter that returns anything but a non-empty array would otherwise
        // produce an empty policy, which browsers treat as no policy at all.
        // Fall back to the unfiltered directives so this fails closed.
        if ( ! is_array( $filtered_directives ) || empty( $filtered_directives ) ) {
            $filtered_directives = $directives;
        }

        return $filtered_directives;
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
        add_filter( 'wp_mail_logging_jquery_confirm_localized_strings', [ $this, 'jquery_confirm_localized_string' ] );
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
     * @deprecated 1.12.0 We are now adding this class in all the WP Mail Logging pages.
     *
     * @param string $classes Space-separated list of CSS classes.
     *
     * @return string
     */
    public function add_admin_body_class( $classes ) {

        return $classes;
    }

    /**
     * The localised strings for the jQuery confirm dialog.
     *
     * @since 1.12.0
     *
     * @param array $strings Localized strings.
     *
     * @return mixed
     */
    public function jquery_confirm_localized_string( $strings ) {

        $strings['delete_log_confirm_msg'] = esc_html__( 'Are you sure you want to delete this log?', 'wp-mail-logging' );

        return $strings;
    }
}
