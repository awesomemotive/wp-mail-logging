<?php

namespace No3x\WPML\Admin;

use Exception;
use No3x\WPML\WPML_Init;
use No3x\WPML\WPML_Utils;

class SettingsTab {

    /**
     * Default settings
     *
     * @since 1.11.0
     *
     * @var array
     */
    const DEFAULT_SETTINGS = [
        'delete-on-deactivation'        => '0',
        'can-see-submission-data'       => 'manage_options',
        'datetimeformat-use-wordpress'  => '0',
        'preferred-mail-format'         => 'html',
        'display-host'                  => '0',
        'display-attachments'           => '0',
        'log-rotation-limit-amout'      => '0',
        'log-rotation-limit-amout-keep' => '75',
        'log-rotation-delete-time'      => '0',
        'log-rotation-delete-time-days' => '30',
        'top-level-menu'                => '1',
    ];

    /**
     * Only instance of this object.
     *
     * @since 1.11.0
     *
     * @var SettingsTab
     */
    private static $instance = null;

    /**
     * Nonce action in saving the settings.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const SAVE_SETTINGS_NONCE_ACTION = 'wp-mail-logging-admin-save-settings';

    /**
     * Option name of the settings.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const OPTIONS_NAME = 'wpml_settings';

    /**
     * Constructor
     *
     * @since 1.11.0
     */
    private function __construct() {
    }

    /**
     * Get the only instance of this object.
     *
     * @since 1.11.0
     *
     * @return SettingsTab
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Hooks that are fired earlier.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function hooks() {

        // Perform save early in `admin_menu` action so it'll know where to put the settings in the admin menu.
        add_action( 'admin_menu', [ $this, 'save_settings'], 5 );
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
        add_action( 'admin_notices', [ 'No3x\WPML\WPML_Utils', 'display_admin_notices' ] );
        add_action( 'wp_mail_logging_admin_tab_content', [ $this, 'display_tab_content' ] );
        add_filter( 'screen_options_show_screen', '__return_false' );
    }

    /**
     * Save settings from $_POST.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function save_settings() {

         // Check if we need to save data.
        $data = filter_input( INPUT_POST, 'wp-mail-logging-setting', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );

        if ( empty( $data ) || empty( $_POST[ self::SAVE_SETTINGS_NONCE_ACTION ] ) || ! wp_verify_nonce( $_POST[ self::SAVE_SETTINGS_NONCE_ACTION ], self::SAVE_SETTINGS_NONCE_ACTION ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $default = self::DEFAULT_SETTINGS;

        // Use the default if we need to reset.
        if ( ! empty( $_POST['wp-mail-logging-setting-reset'] ) ) {

            if ( update_option( self::OPTIONS_NAME, self::DEFAULT_SETTINGS ) ) {
                WPML_Utils::add_admin_notice( esc_html__( 'Settings saved!', 'wp-mail-logging' ), WPML_Utils::ADMIN_NOTICE_SUCCESS, true );
            }

            return;
        }

        $save_data = wp_parse_args( $data, $default );

        if ( empty( $data['top-level-menu'] ) ) {
            $save_data['top-level-menu'] = '0';
        }

        // Sanitize the data.
        $save_data['log-rotation-limit-amout-keep'] = absint( $save_data['log-rotation-limit-amout-keep'] );
        $save_data['log-rotation-delete-time-days'] = absint( $save_data['log-rotation-delete-time-days'] );

        if ( update_option( self::OPTIONS_NAME, $save_data ) ) {
            WPML_Utils::add_admin_notice( esc_html__( 'Settings saved!', 'wp-mail-logging' ), WPML_Utils::ADMIN_NOTICE_SUCCESS, true );
        }
    }

    /**
     * Enqueue settings tab scripts.
     *
     * @since 1.11.0
     *
     * @return void
     *
     * @throws Exception
     */
    public function enqueue_scripts() {

        $assets_url  = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
        $plugin_meta = WPML_Init::getInstance()->getService( 'plugin-meta' );

        if ( empty( $plugin_meta['version'] ) ) {
            return;
        }

        wp_enqueue_script(
            'wp-mail-logging-admin-settings',
            $assets_url . '/js/wp-mail-logging-admin-settings.js',
            [ 'jquery' ],
            $plugin_meta['version']
        );
    }

    /**
     * Display Settings form.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function display_tab_content() {

        $saved_settings = self::get_settings( self::DEFAULT_SETTINGS );
        ?>
            <form id="wp-mail-logging-setting-tab-form" method="POST">
                <?php wp_nonce_field( self::SAVE_SETTINGS_NONCE_ACTION, self::SAVE_SETTINGS_NONCE_ACTION ); ?>

                <?php

                $this->create_group_field_header(
                    'general-settings',
                    __( 'General Settings', 'wp-mail-logging' ),
                    __( 'Change your WP Mail Logging settings.', 'wp-mail-logging' )
                );

                $this->create_field(
                    [
                        'bordered' => false,
                        'desc'     => __( 'Select the minimum role required to view submission data.', 'wp-mail-logging' ),
                        'id'       => 'can-see-submission-data',
                        'label'    => __( 'Can See Submission data', 'wp-mail-logging' ),
                        'options'  => $this->get_capabilities(),
                        'type'     => 'select',
                        'value'    => $saved_settings['can-see-submission-data'],
                    ]
                );

                $datetime_format_field_desc = sprintf(
                    /* translators: %s: Date time in WP format. */
                    __( 'Use format from WordPress settings (%s)', 'wp-mail-logging' ),
                    date_i18n( $this->get_wp_date_time_format() )
                );

                $this->create_field(
                    [
                        'bordered' => false,
                        'desc'     => $datetime_format_field_desc,
                        'id'       => 'datetimeformat-use-wordpress',
                        'label'    => __( 'WP Date Time Format', 'wp-mail-logging' ),
                        'type'     => 'checkbox-toggle',
                        'value'    => $saved_settings['datetimeformat-use-wordpress'],
                    ]
                );

                $this->create_field(
                    [
                        'bordered' => false,
                        'desc'     => __( 'Select your preferred display format.', 'wp-mail-logging' ),
                        'id'       => 'preferred-mail-format',
                        'label'    => __( 'Default Format for Message', 'wp-mail-logging' ),
                        'options'  => [
                            'html' => 'HTML',
                            'raw'  => 'Raw',
                            'json' => 'JSON',
                        ],
                        'type'     => 'select',
                        'value'    => $saved_settings['preferred-mail-format'],
                    ]
                );

                $this->create_field(
                    [
                        'bordered' => false,
                        'desc'     => __( 'Display the IP of the host WordPress is running on. This is useful when running WP Mail Logging on multiple servers at the same time.', 'wp-mail-logging' ),
                        'id'       => 'display-host',
                        'label'    => __( 'Display Host', 'wp-mail-logging' ),
                        'type'     => 'checkbox-toggle',
                        'value'    => $saved_settings['display-host'],
                    ]
                );

                $this->create_field(
                    [
                        'bordered' => false,
                        'desc'     => __( 'Display the attachments in Email Logs table.', 'wp-mail-logging' ),
                        'id'       => 'display-attachments',
                        'label'    => __( 'Display Attachments', 'wp-mail-logging' ),
                        'type'     => 'checkbox-toggle',
                        'value'    => empty( $saved_settings['display-attachments'] ) ? '0' : '1',
                    ]
                );

                $this->create_field(
                    [
                        'bordered' => false,
                        'desc'     => __( 'Disabling this will condense navigation and move WP Mail Log under the WordPress Tools menu.', 'wp-mail-logging' ),
                        'id'       => 'top-level-menu',
                        'label'    => __( 'Top Level Menu', 'wp-mail-logging' ),
                        'type'     => 'checkbox-toggle',
                        'value'    => ! isset( $saved_settings['top-level-menu'] ) ? '1' : $saved_settings['top-level-menu'],
                    ]
                );

                $this->create_field(
                    [
                        'bordered'     => true,
                        'desc'         => __( 'Delete all WP Mail Logging data on deactivation.', 'wp-mail-logging' ),
                        'id'           => 'delete-on-deactivation',
                        'label'        => __( 'Cleanup', 'wp-mail-logging' ),
                        'type'         => 'checkbox-toggle',
                        'value'        => $saved_settings['delete-on-deactivation'],
                    ]
                );

                $this->create_group_field_header(
                    'log-rotation',
                    __( 'Log Rotation', 'wp-mail-logging' ),
                    __( 'Save space by deleting logs regularly.', 'wp-mail-logging' )
                );

                $this->create_field(
                    [
                        'desc'       => __( 'Set up an automated cleanup routine that is triggered when a certain amount of logs have been saved.', 'wp-mail-logging' ),
                        'id'         => 'log-rotation-limit-amout',
                        'label'      => __( 'Cleanup by Amount', 'wp-mail-logging' ),
                        'type'       => 'checkbox-toggle',
                        'toggles_id' => 'log-rotation-limit-amout-keep',
                        'value'      => $saved_settings['log-rotation-limit-amout'],
                    ]
                );

                $this->create_field(
                    [
                        'desc'           => __( 'Delete email logs after this amount has been reached.', 'wp-mail-logging' ),
                        'id'             => 'log-rotation-limit-amout-keep',
                        'type'           => 'number',
                        'initial_hidden' => empty( $saved_settings['log-rotation-limit-amout'] ), // Hide if the toggle above is "Off".
                        'value'          => $saved_settings['log-rotation-limit-amout-keep'],
                    ]
                );

                $this->create_field(
                    [
                        'desc'       => __( 'Set up an automated cleanup routine that is triggered after a certain amount of time has passed.', 'wp-mail-logging' ),
                        'id'         => 'log-rotation-delete-time',
                        'label'      => __( 'Cleanup by Time', 'wp-mail-logging' ),
                        'type'       => 'checkbox-toggle',
                        'toggles_id' => 'log-rotation-delete-time-days',
                        'value'      => $saved_settings['log-rotation-delete-time'],
                    ]
                );

                $this->create_field(
                    [
                        'desc'           => __( 'Delete email logs after this many days.', 'wp-mail-logging' ),
                        'id'             => 'log-rotation-delete-time-days',
                        'type'           => 'number',
                        'initial_hidden' => empty( $saved_settings['log-rotation-delete-time'] ), // Hide if the toggle above is "Off".,
                        'value'          => $saved_settings['log-rotation-delete-time-days'],
                    ]
                );
                ?>

                <div id="wp-mail-logging-settings-bottom">
                    <div>
                        <button type="submit" class="wp-mail-logging-btn wp-mail-logging-btn-lg wp-mail-logging-btn-orange"><?php esc_html_e( 'Save Settings', 'wp-mail-logging' ); ?></button>
                    </div>
                    <div id="wp-mail-logging-settings-reset">
                        <input id="wp-mail-logging-settings-reset-link" name="wp-mail-logging-setting-reset" type="submit" value="<?php esc_html_e( 'Reset to Default', 'wp-mail-logging' ); ?>">
                    </div>
                </div>

                <p class="wp-mail-logging-submit">
                </p>
            </form>
        <?php
    }

    /**
     * Get saved WordPress date time format.
     *
     * @since 1.11.0
     *
     * @return string
     */
    private function get_wp_date_time_format() {
        // Get saved WP date format.
        $date_format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );

        $date_format = empty( $date_format ) ? 'F j, Y' : $date_format;
        $time_format = empty( $time_format ) ? 'g:i a' : $time_format;

        return $date_format . " " . $time_format;
    }

    /**
     * Display the Settings form.
     *
     * Get the saved settings.
     *
     * @since 1.11.0
     *
     * @param array $default Default to return if option is not found.
     *
     * @return array
     */
    public static function get_settings( $default = [] ) {

        return get_option( self::OPTIONS_NAME, $default );
    }

    /**
     * Create group fields heading.
     *
     * @since 1.11.0
     *
     * @param string $id      ID of the group heading.
     * @param string $heading Heading label.
     * @param string $desc    Group description.
     *
     *
     * @return void
     */
    private function create_group_field_header( $id, $heading, $desc ) {
        ?>
        <div id="wp-mail-logging-setting-row-heading-<?php echo esc_attr( $id ); ?>" class="wp-mail-logging-setting-row wp-mail-logging-setting-row-no-border wp-mail-logging-setting-row-content wp-mail-logging-clearfix section-heading">
            <div class="wp-mail-logging-setting-field">
                <h2><?php echo esc_html( $heading ) ?></h2>
            </div>

            <p>
                <?php echo esc_html( $desc ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Renders a field for the Settings form.
     *
     * @since 1.11.0
     *
     * @param array $args
     * @return void
     */
    private function create_field( $args ) {

        $defaults = [
            'id'             => '',
            'label'          => '',
            'desc'           => '',
            'toggle_label'   => [
                'checked'   => __( 'On', 'wp-mail-logging' ),
                'unchecked' => __( 'Off', 'wp-mail-logging' ),
            ],
            'bordered'       => false,
            'options'        => [],
            'type'           => 'checkbox-toggle',
            'initial_hidden' => false,
            'toggles_id'     => '', // ID of the field this field toggles.
            'value'          => '', // Value of the field.
        ];

        $args                 = wp_parse_args( $args, $defaults );
        $no_border_style      = $args['bordered'] ? '' : 'wp-mail-logging-setting-row-no-border';
        $initial_hidden_style = $args['initial_hidden'] ? 'wp-mail-logging-hide' : '';
        ?>
        <div id="wp-mail-logging-setting-tab-row-<?php echo esc_attr( $args['id'] ); ?>"
             class="wp-mail-logging-setting-row wp-mail-logging-setting-row-<?php echo esc_attr( $args['type'] ); ?> wp-mail-logging-clearfix <?php echo esc_attr( $no_border_style ); ?> <?php echo esc_attr( $initial_hidden_style ); ?>">
            <?php
            if ( ! empty( $args['label'] ) ) { ?>
                <div class="wp-mail-logging-setting-label">
                    <label for="wp-mail-logging-setting-<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
                </div>
            <?php }
            ?>

            <div class="wp-mail-logging-setting-field">
                <label for="wp-mail-logging-setting-<?php echo esc_attr( $args['id'] ); ?>">

                    <?php
                    switch ( $args['type'] ) {
                        case 'checkbox-toggle':
                            $toggle_class = empty( $args['toggles_id'] ) ? '' : 'wp-mail-logging-settings-toggle';
                            ?>
                                <input class="<?php echo esc_attr( $toggle_class ); ?>"
                                       data-toggles-id="<?php echo esc_attr( $args['toggles_id'] ); ?>"
                                       type="checkbox"
                                       id="wp-mail-logging-setting-<?php echo esc_attr( $args['id'] ); ?>"
                                       name="wp-mail-logging-setting[<?php echo esc_attr( $args['id'] ); ?>]"
                                       value="1" <?php checked( $args['value'], 1 ); ?>/>
                                <span class="wp-mail-logging-setting-toggle-switch"></span>
                                <span class="wp-mail-logging-setting-toggle-checked-label"><?php echo esc_html( $args['toggle_label']['checked'] ); ?></span>
                                <span class="wp-mail-logging-setting-toggle-unchecked-label"><?php echo esc_html( $args['toggle_label']['unchecked'] ); ?></span>
                            <?php
                            break;
                        case 'select':
                            ?>
                            <select id="wp-mail-logging-setting-<?php echo esc_attr( $args['id'] ); ?>" name="wp-mail-logging-setting[<?php echo esc_attr( $args['id'] ); ?>]">
                            <?php foreach ( $args['options'] as $val => $label ) { ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $args['value'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php } ?>
                            </select>
                            <?php
                            break;
                        case 'number':
                            ?>
                            <input type="number" name="wp-mail-logging-setting[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $args['value'] ); ?>" id="wp-mail-logging-setting[<?php echo esc_attr( $args['id'] ); ?>]" spellcheck="false" />
                            <?php
                            break;
                    }
                    ?>
                </label>
                <p class="desc">
                    <?php echo esc_html( $args['desc'] ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Returns all the capabilities in WP.
     *
     * @since 1.11.0
     *
     * @return array
     */
    private function get_capabilities() {
        $capabilities = [];

        $wp_roles = wp_roles();

        foreach ( $wp_roles->roles as $role ) {
            foreach ( $role['capabilities'] as $key => $val ) {
                $capabilities[ $key ] = ucwords( str_replace( '_', ' ', $key ) );
            }
        }

        return $capabilities;
    }
}
