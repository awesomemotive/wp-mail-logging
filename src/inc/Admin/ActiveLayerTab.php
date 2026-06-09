<?php

namespace No3x\WPML\Admin;

use No3x\WPML\Helpers\PluginSilentUpgrader;
use No3x\WPML\WPML_Init;

/**
 * Renders the "Spam Protection" tab that recommends, installs and activates
 * the ActiveLayer anti-spam plugin from WordPress.org.
 *
 * @since {VERSION}
 */
class ActiveLayerTab {

    /**
     * Nonce action for the ActiveLayer page.
     *
     * @since {VERSION}
     *
     * @var string
     */
    const NONCE_ACTION = 'wp-mail-logging-admin-activelayer';

    /**
     * Configuration.
     *
     * @since {VERSION}
     *
     * @var string[]
     */
    private $config = [
        'plugin'       => 'activelayer-anti-spam-spam-protection-for-forms-comments/activelayer-anti-spam-spam-protection-for-forms-comments.php',
        'wporg_url'    => 'https://wordpress.org/plugins/activelayer-anti-spam-spam-protection-for-forms-comments/',
        'download_url' => 'https://downloads.wordpress.org/plugin/activelayer-anti-spam-spam-protection-for-forms-comments.zip',
        'settings_url' => 'admin.php?page=activelayer-settings',
    ];

    /**
     * Runtime data used for generating page HTML.
     *
     * @since {VERSION}
     *
     * @var array
     */
    private $output_data = [];

    /**
     * Only instance of this object.
     *
     * @since {VERSION}
     *
     * @var ActiveLayerTab
     */
    private static $instance = null;

    /**
     * Get the only instance of this object.
     *
     * @since {VERSION}
     *
     * @return ActiveLayerTab
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @since {VERSION}
     */
    private function __construct() {
    }

    /**
     * Hooks that are fired earlier.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function hooks() {

        add_action( 'wp_ajax_wp_mail_logging_install_activelayer', [ $this, 'ajax_install_plugin' ] );
        add_action( 'wp_ajax_wp_mail_logging_activate_activelayer', [ $this, 'ajax_activate_plugin' ] );
    }

    /**
     * AJAX operation to install and activate the ActiveLayer plugin.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function ajax_install_plugin() {

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $generic_error = __( 'There was an error while performing your request.', 'wp-mail-logging' );

        if ( ! $this->can_install_plugin() ) {
            wp_send_json_error( esc_html( $generic_error ) );
        }

        $error = esc_html__( 'Could not install the plugin. Please download and install it manually.', 'wp-mail-logging' );
        $url   = esc_url_raw( self::get_url() );

        ob_start();

        $creds = request_filesystem_credentials(
            $url,
            '',
            false,
            false,
            null
        );

        // Hide the filesystem credentials form.
        ob_end_clean();

        if ( $creds === false || ! WP_Filesystem( $creds ) ) {
            wp_send_json_error( $error );
        }

        /*
         * We do not need any extra credentials if we have gotten this far, so let's install the plugin.
         */
        require_once WP_MAIL_LOGGING_PLUGIN_DIR . 'src/inc/class-install-skin.php';

        // Do not allow WordPress to search/download translations, as this will break JS output.
        remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );

        // Create the plugin upgrader with our custom skin.
        $installer = new PluginSilentUpgrader( new \WP_Mail_Logging_Install_Skin() );

        // Error check.
        if ( ! method_exists( $installer, 'install' ) ) {
            wp_send_json_error( $error );
        }

        $installer->install( $this->config['download_url'] );

        // Flush the cache and return the newly installed plugin basename.
        wp_cache_flush();

        if ( empty( $installer->plugin_info() ) ) {
            wp_send_json_error( $error );
        }

        $result = [
            'msg'          => $generic_error,
            'is_activated' => false,
            'basename'     => $installer->plugin_info(),
        ];

        if ( ! current_user_can( 'activate_plugins' ) ) {

            $result['msg'] = esc_html__( 'Plugin installed.', 'wp-mail-logging' );

            wp_send_json_success( $result );
        }

        // Activate the plugin silently.
        $activated = activate_plugin( $installer->plugin_info() );

        if ( ! is_wp_error( $activated ) ) {

            $result['is_activated'] = true;
            $result['msg']          = esc_html__( 'Plugin installed & activated.', 'wp-mail-logging' );

            wp_send_json_success( $result );
        }

        // Fallback error just in case.
        wp_send_json_error( $result );
    }

    /**
     * AJAX operation to activate the ActiveLayer plugin.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function ajax_activate_plugin() {

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( esc_html__( 'Plugin activation is disabled for you on this site.', 'wp-mail-logging' ) );
        }

        if ( empty( $_POST['plugin'] ) ) {
            wp_send_json_error( esc_html__( 'There was an error while performing your request.', 'wp-mail-logging' ) );
        }

        $activate = activate_plugins( sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) );

        if ( ! is_wp_error( $activate ) ) {
            wp_send_json_success( esc_html__( 'Plugin activated.', 'wp-mail-logging' ) );
        }

        wp_send_json_error( esc_html__( 'Could not activate the plugin. Please activate it on the Plugins page.', 'wp-mail-logging' ) );
    }

    /**
     * Only add hooks here that are invoked after `current_screen` action hook.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function screen_hooks() {

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_mail_logging_admin_tab_content', [ $this, 'display_tab_content' ] );
        add_filter( 'screen_options_show_screen', '__return_false' );
    }

    /**
     * Enqueue scripts in the ActiveLayer tab.
     *
     * @since {VERSION}
     *
     * @return void
     *
     * @throws \Exception When the plugin service is unavailable.
     */
    public function enqueue_scripts() {

        $assets_url  = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
        $plugin_meta = WPML_Init::getInstance()->getService( 'plugin-meta' );

        $min = '';

        if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
            $min = '.min';
        }

        wp_enqueue_style(
            'wp-mail-logging-admin-activelayer',
            $assets_url . "/css/wp-mail-logging-activelayer{$min}.css",
            [],
            $plugin_meta['version']
        );

        wp_enqueue_script(
            'wp-mail-logging-admin-activelayer',
            $assets_url . "/js/wp-mail-logging-admin-activelayer{$min}.js",
            [ 'jquery' ],
            $plugin_meta['version'],
            true
        );

        $error_could_not_install = sprintf(
            wp_kses( /* translators: %s - ActiveLayer plugin download URL. */
                __( 'Could not install the plugin automatically. Please <a href="%s">download</a> it and install it manually.', 'wp-mail-logging' ),
                [
                    'a' => [
                        'href' => true,
                    ],
                ]
            ),
            esc_url( $this->config['download_url'] )
        );

        $error_could_not_activate = sprintf(
            wp_kses( /* translators: %s - Plugins page URL. */
                __( 'Could not activate the plugin. Please activate it on the <a href="%s">Plugins page</a>.', 'wp-mail-logging' ),
                [
                    'a' => [
                        'href' => true,
                    ],
                ]
            ),
            esc_url( admin_url( 'plugins.php' ) )
        );

        wp_localize_script(
            'wp-mail-logging-admin-activelayer',
            'wp_mail_logging_admin_activelayer',
            [
                'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
                'nonce'                    => wp_create_nonce( self::NONCE_ACTION ),
                'activating'               => esc_html__( 'Activating...', 'wp-mail-logging' ),
                'installing'               => esc_html__( 'Installing...', 'wp-mail-logging' ),
                'activated'                => esc_html__( 'ActiveLayer Installed & Activated', 'wp-mail-logging' ),
                'download_now'             => esc_html__( 'Download Now', 'wp-mail-logging' ),
                'plugins_page'             => esc_html__( 'Go to Plugins page', 'wp-mail-logging' ),
                'error_could_not_install'  => $error_could_not_install,
                'error_could_not_activate' => $error_could_not_activate,
                'manual_install_url'       => $this->config['download_url'],
                'manual_activate_url'      => admin_url( 'plugins.php' ),
                'settings'                 => esc_html__( 'Go to ActiveLayer Settings', 'wp-mail-logging' ),
            ]
        );

        // Enqueue Lity.
        wp_enqueue_script(
            'wp-mail-logging-admin-lity',
            $plugin_meta['uri'] . 'lib/lity/lity.min.js',
            [],
            '2.4.1',
            true
        );

        wp_enqueue_style(
            'wp-mail-logging-admin-lity',
            $plugin_meta['uri'] . 'lib/lity/lity.min.css',
            [],
            '2.4.1'
        );
    }

    /**
     * Display the ActiveLayer tab content.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function display_tab_content() {

        // Admin notices display after the first heading tag.
        echo '<h1 class="wp-mail-logging-hide">' . esc_html__( 'ActiveLayer', 'wp-mail-logging' ) . '</h1>';

        echo '<div id="wp-mail-logging-admin-activelayer" class="wrap">';
        $this->output_section_heading();
        $this->output_section_screenshot();
        $this->output_section_step_install();
        $this->output_section_step_setup();
        echo '</div>';
    }

    /**
     * Generate and output the heading section HTML.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function output_section_heading() {

        $assets_url = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();

        // Heading section.
        printf(
            '<section class="top">
                <img class="img-top" src="%1$s" srcset="%2$s 2x" alt="%3$s"/>
                <h1>%4$s</h1>
                <p>%5$s</p>
            </section>',
            esc_url( $assets_url . '/images/activelayer/activelayer-logo.png' ),
            esc_url( $assets_url . '/images/activelayer/activelayer-logo@2x.png' ),
            esc_attr__( 'ActiveLayer', 'wp-mail-logging' ),
            esc_html__( 'Block Form & Comment Spam Without CAPTCHAs', 'wp-mail-logging' ),
            esc_html__( 'AI-powered spam protection. No friction for real visitors, higher form conversions. Free tier available, no credit-card required. 1,000 checks free.', 'wp-mail-logging' )
        );
    }

    /**
     * Generate and output the screenshot section HTML.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function output_section_screenshot() {

        $assets_url  = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
        $plugin_meta = WPML_Init::getInstance()->getService( 'plugin-meta' );

        // Screenshot section.
        printf(
            '<section class="screenshot">
                <div class="cont">
                    <img src="%1$s" alt="%2$s"/>
                    <a href="%3$s" class="hover" data-lity></a>
                </div>
                <ul>
                    <li>%4$s</li>
                    <li>%5$s</li>
                    <li>%6$s</li>
                    <li>%7$s</li>
                </ul>
            </section>',
            esc_url( $assets_url . '/images/activelayer/screenshot-tnail.png?ver=' . $plugin_meta['version'] ),
            esc_attr__( 'ActiveLayer screenshot', 'wp-mail-logging' ),
            esc_url( $assets_url . '/images/activelayer/screenshot-full.png?ver=' . $plugin_meta['version'] ),
            esc_html__( 'Blocks form, comment and registration spam without CAPTCHAs or puzzles.', 'wp-mail-logging' ),
            esc_html__( 'Asynchronous checks keep your forms instant, with zero added latency.', 'wp-mail-logging' ),
            esc_html__( 'Protects WPForms, Contact Form 7, Gravity Forms, Elementor & Fluent Forms.', 'wp-mail-logging' ),
            esc_html__( 'Also guards WordPress comments, WooCommerce reviews & registration.', 'wp-mail-logging' )
        );
    }

    /**
     * Generate and output the step 'Install' section HTML.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function output_section_step_install() {

        $step = $this->get_data_step_install();

        if ( empty( $step ) ) {
            return;
        }

        $button_format       = '<button class="button %3$s" data-plugin="%1$s" data-action="%4$s">%2$s</button>';
        $button_allowed_html = [
            'button' => [
                'class'       => true,
                'data-plugin' => true,
                'data-action' => true,
            ],
        ];

        if ( ! $this->output_data['plugin_installed'] && ! $this->can_install_plugin() ) {
            $button_format       = '<a class="link" href="%1$s" target="_blank" rel="nofollow noopener">%2$s <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';
            $button_allowed_html = [
                'a'    => [
                    'class'  => true,
                    'href'   => true,
                    'target' => true,
                    'rel'    => true,
                ],
                'span' => [
                    'class'       => true,
                    'aria-hidden' => true,
                ],
            ];
        }

        $assets_url = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();

        $button = sprintf( $button_format, esc_attr( $step['plugin'] ), esc_html( $step['button_text'] ), esc_attr( $step['button_class'] ), esc_attr( $step['button_action'] ) );

        printf(
            '<section class="step step-install">
                <aside class="num">
                    <img src="%1$s" alt="%2$s" />
                    <i class="loader hidden"></i>
                </aside>
                <div>
                    <h2>%3$s</h2>
                    <p>%4$s</p>
                    %5$s
                </div>
            </section>',
            esc_url( $assets_url . '/images/activelayer/' . $step['icon'] ),
            esc_attr__( 'Step 1', 'wp-mail-logging' ),
            esc_html( $step['heading'] ),
            esc_html( $step['description'] ),
            wp_kses( $button, $button_allowed_html )
        );
    }

    /**
     * Step 'Install' data.
     *
     * @since {VERSION}
     *
     * @return array Step data.
     */
    private function get_data_step_install() {

        $step                = [];
        $step['heading']     = esc_html__( 'Install and Activate ActiveLayer', 'wp-mail-logging' );
        $step['description'] = esc_html__( 'Install ActiveLayer from the WordPress.org plugin repository.', 'wp-mail-logging' );

        $this->output_data['all_plugins']      = get_plugins();
        $this->output_data['plugin_installed'] = array_key_exists( $this->config['plugin'], $this->output_data['all_plugins'] );
        $this->output_data['plugin_activated'] = false;

        if ( ! $this->output_data['plugin_installed'] ) {
            $step['icon']          = 'step-1.svg';
            $step['button_text']   = esc_html__( 'Install ActiveLayer', 'wp-mail-logging' );
            $step['button_class']  = 'button-primary';
            $step['button_action'] = 'install';
            $step['plugin']        = '';

            if ( ! $this->can_install_plugin() ) {
                $step['heading']     = esc_html__( 'ActiveLayer', 'wp-mail-logging' );
                $step['description'] = '';
                $step['button_text'] = esc_html__( 'ActiveLayer on WordPress.org', 'wp-mail-logging' );
                $step['plugin']      = $this->config['wporg_url'];
            }

            return $step;
        }

        $this->output_data['plugin_activated'] = $this->is_activelayer_activated();
        $step['icon']                          = $this->output_data['plugin_activated'] ? 'step-complete.svg' : 'step-1.svg';
        $step['button_text']                   = $this->output_data['plugin_activated'] ? esc_html__( 'ActiveLayer Installed & Activated', 'wp-mail-logging' ) : esc_html__( 'Activate ActiveLayer', 'wp-mail-logging' );
        $step['button_class']                  = $this->output_data['plugin_activated'] ? 'grey disabled' : 'button-primary';
        $step['button_action']                 = $this->output_data['plugin_activated'] ? '' : 'activate';
        $step['plugin']                        = $this->config['plugin'];

        return $step;
    }

    /**
     * Determine if plugin installation is allowed.
     *
     * @since {VERSION}
     *
     * @return bool
     */
    private function can_install_plugin() {

        if ( ! current_user_can( 'install_plugins' ) || ! wp_is_file_mod_allowed( 'wp_mail_logging_can_install_plugin' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Whether the ActiveLayer plugin is active or not.
     *
     * @since {VERSION}
     *
     * @return bool True if the ActiveLayer plugin is active.
     */
    private function is_activelayer_activated() {

        return is_plugin_active( $this->config['plugin'] );
    }

    /**
     * Generate and output the step 'Setup' section HTML.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function output_section_step_setup() {

        $step = $this->get_data_step_setup();

        if ( empty( $step ) ) {
            return;
        }

        $assets_url = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();

        printf(
            '<section class="step step-setup %1$s">
                <aside class="num">
                    <img src="%2$s" alt="%3$s" />
                    <i class="loader hidden"></i>
                </aside>
                <div>
                    <h2>%4$s</h2>
                    <p>%5$s</p>
                    <button class="button %6$s" data-url="%7$s">%8$s</button>
                </div>
            </section>',
            esc_attr( $step['section_class'] ),
            esc_url( $assets_url . '/images/activelayer/' . $step['icon'] ),
            esc_attr__( 'Step 2', 'wp-mail-logging' ),
            esc_html__( 'Connect Your ActiveLayer Account', 'wp-mail-logging' ),
            esc_html__( 'Create a free account and verify your API key to start blocking spam.', 'wp-mail-logging' ),
            esc_attr( $step['button_class'] ),
            esc_url( admin_url( $this->config['settings_url'] ) ),
            esc_html( $step['button_text'] )
        );
    }

    /**
     * Step 'Setup' data.
     *
     * @since {VERSION}
     *
     * @return array Step data.
     */
    protected function get_data_step_setup() {

        $step = [
            'icon' => 'step-2.svg',
        ];

        if ( $this->output_data['plugin_activated'] ) {
            $step['section_class'] = '';
            $step['button_class']  = 'button-primary';
            $step['button_text']   = esc_html__( 'Go to ActiveLayer Settings', 'wp-mail-logging' );
        } else {
            $step['section_class'] = 'grey';
            $step['button_class']  = 'grey disabled';
            $step['button_text']   = esc_html__( 'Connect Your Account', 'wp-mail-logging' );
        }

        return $step;
    }

    /**
     * Return the ActiveLayer tab url.
     *
     * @since {VERSION}
     *
     * @return string
     */
    public static function get_url() {

        return add_query_arg(
            [
                'page' => 'wpml_plugin_log',
                'tab'  => 'activelayer',
            ],
            admin_url( 'admin.php' )
        );
    }
}
