<?php

namespace No3x\WPML\Settings;

/**
 * ReduxFramework Sample Config File
 * For full documentation, please visit: http://docs.reduxframework.com/
 */

if (!class_exists('WPML_Redux_Framework_config')) {

    class WPML_Redux_Framework_config {

        public $args = array();
        public $sections = array();
        public $theme;
        public $ReduxFramework;
        protected $plugin_meta = array();

        public function __construct( $plugin_meta ) {
            $this->plugin_meta = $plugin_meta;

            if ( ! class_exists( 'ReduxFramework' ) ) {
                return;
            }

            // This is needed. Bah WordPress bugs.  ;)
            if ( true == \Redux_Helpers::isTheme( __FILE__ ) ) {
                $this->initSettings();
            } else {
                add_action( 'plugins_loaded', array( $this, 'initSettings' ), 10 );
            }

        }

        public function initSettings() {

            // Just for demo purposes. Not needed per say.
            $this->theme = wp_get_theme();

            // Set the default arguments
            $this->setArguments();

            // Set a few help tabs so you can see how it's done
            // $this->setHelpTabs();

            // Create the sections and fields
            $this->setSections();

            if ( ! isset( $this->args['opt_name'] ) ) { // No errors please
                return;
            }

            // If Redux is running as a plugin, this will remove the demo notice and links
            //add_action( 'redux/loaded', array( $this, 'remove_demo' ) );

            // Function to test the compiler hook and demo CSS output.
            // Above 10 is a priority, but 2 in necessary to include the dynamically generated CSS to be sent to the function.
            //add_filter('redux/options/'.$this->args['opt_name'].'/compiler', array( $this, 'compiler_action' ), 10, 3);

            // Change the arguments after they've been declared, but before the panel is created
            //add_filter('redux/options/'.$this->args['opt_name'].'/args', array( $this, 'change_arguments' ) );

            // Change the default value of a field after it's been set, but before it's been useds
            //add_filter('redux/options/'.$this->args['opt_name'].'/defaults', array( $this,'change_defaults' ) );

            // Dynamically add a section. Can be also used to modify sections/fields
            //add_filter('redux/options/' . $this->args['opt_name'] . '/sections', array($this, 'dynamic_section'));

            $this->ReduxFramework = new \ReduxFramework( $this->sections, $this->args );

            // Disable the Redux demo
            if ( method_exists( "Redux", "disable_demo" ) ) {
                \Redux::disable_demo();
            }
        }

        // Remove the demo link and the notice of integrated demo from the redux-framework plugin
        function remove_demo() {

            // Used to hide the demo mode link from the plugin page. Only used when Redux is a plugin.
            if ( class_exists( 'ReduxFrameworkPlugin' ) ) {
                remove_filter( 'plugin_row_meta', array(
                    ReduxFrameworkPlugin::instance(),
                    'plugin_metalinks'
                ), null, 2 );

                // Used to hide the activation notice informing users of the demo panel. Only used when Redux is a plugin.
                remove_action( 'admin_notices', array( ReduxFrameworkPlugin::instance(), 'admin_notices' ) );
            }
        }

        public function setSections() {

            // ACTUAL DECLARATION OF SECTIONS
            $this->sections[] = array(
                'title'     => __('General Settings', 'wp-mail-logging'),
                'desc'      => __('', 'wp-mail-logging'),
                'icon'      => 'el-icon-cogs',
                // 'submenu' => false, // Setting submenu to false on a given section will hide it from the WordPress sidebar menu!
                'fields'    => array(

                    array(
                        'id'        => 'delete-on-deactivation',
                        'type'      => 'switch',
                        'title'     => __('Cleanup', 'wp-mail-logging' ),
                        'subtitle'  => __('Delete all data on deactivation? (emails and settings)?', 'wp-mail-logging'),
                        'default'   => 0,
                        'on'        => __(__('Enabled', 'wp-mail-logging' ), 'wp-mail-logging' ),
                        'off'       => __(__('Disabled', 'wp-mail-logging' ), 'wp-mail-logging' ),
                    ),
                    array(
                        'id'        => 'can-see-submission-data',
                        'type'      => 'select',
                        'data'      => 'capabilities',
                        'default' 	=> 'manage_options',
                        'title'     => __('Can See Submission data', 'wp-mail-logging'),
                        'subtitle'  => __('Select the minimum role.', 'wp-mail-logging'),
                    ),
                    array(
                        'id'        => 'datetimeformat-use-wordpress',
                        'type'      => 'switch',
                        'title'     => __('WordPress Date Time Format', 'wp-mail-logging' ),
                        'subtitle'  => sprintf( __( "Use format from WordPress settings (%s)", 'wp-mail-logging' ), date_i18n( $this->wordpress_default_format(), current_time( 'timestamp' ) ) ),
                        'default'   => 0,
                        'on'        => __('Enabled', 'wp-mail-logging' ),
                        'off'       => __('Disabled', 'wp-mail-logging' ),
                    ),
                    array(
                        'id'        => 'preferred-mail-format',
                        'type'      => 'select',
                        'options' 	=> array(
                            'html' => 'html',
                            'raw' => 'raw',
                            'json' => 'json'
                        ),
                        'default' 	=> 'html',
                        'title'     => __('Default Format for Message', 'wp-mail-logging'),
                        'subtitle'  => __('Select your preferred display format.', 'wp-mail-logging'),
                    ),
                    array(
                        'id'        => 'display-host',
                        'type'      => 'switch',
                        'title'     => __('Display Host', 'wp-mail-logging' ),
                        'subtitle'  => __('Display host column in list.', 'wp-mail-logging'),
                        'hint'     => array(
                            'title'   => 'Host',
                            'content' => 'Display the IP of the host WordPress is running on. This is useful when running it on multiple servers at the same time.',
                        ),
                        'default'   => 0,
                        'on'        => __('Enabled', 'wp-mail-logging' ),
                        'off'       => __('Disabled', 'wp-mail-logging' ),
                    ),
                    array(
                        'id'        => 'section-log-rotation-start',
                        'type'      => 'section',
                        'title'     => __('Log Rotation', 'wp-mail-logging' ),
                        'subtitle'  => __('Save space by deleting logs regularly.', 'wp-mail-logging'),
                        'indent'    => true, // Indent all options below until the next 'section' option is set.
                    ),
                    array(
                        'id'        => 'log-rotation-limit-amout',
                        'type'      => 'switch',
                        'title'     => __('Cleanup by Amount', 'wp-mail-logging' ),
                        'subtitle'  => __('Setup a automated cleanup routine!', 'wp-mail-logging'),
                        'default'   => 0,
                        'on'        => __('Enabled', 'wp-mail-logging' ),
                        'off'       => __('Disabled', 'wp-mail-logging' ),
                    ),
                    array(
                        'id'            => 'log-rotation-limit-amout-keep',
                        'type'          => 'slider',
                        'required'  => array('log-rotation-limit-amout', '=', '1'),
                        'title'         => __('Amount', 'wp-mail-logging' ),
                        'subtitle'      => __('When should mails are deleted?', 'wp-mail-logging'),
                        'desc'      	=> __('Cleanup when the stored mails exceed...', 'wp-mail-logging'),
                        'default'       => 75,
                        'min'           => 25,
                        'step'          => 50,
                        'max'           => 3000,
                        'display_value' => 'text'
                    ),
                    array(
                        'id'        => 'log-rotation-delete-time',
                        'type'      => 'switch',
                        'title'     => __('Cleanup by Time', 'wp-mail-logging' ),
                        'subtitle'  => __('Setup a automated cleanup routine!', 'wp-mail-logging'),
                        'default'   => 0,
                        'on'        => __('Enabled', 'wp-mail-logging' ),
                        'off'       => __('Disabled', 'wp-mail-logging' ),
                    ),
                    array(
                        'id'            => 'log-rotation-delete-time-days',
                        'type'          => 'slider',
                        'required'  	=> array('log-rotation-delete-time', '=', '1'),
                        'title'         => __('Time', 'wp-mail-logging' ),
                        'subtitle'      => __('When should mails are deleted?', 'wp-mail-logging'),
                        'desc'      	=> __('Delete mails older than days...', 'wp-mail-logging'),
                        'default'       => 30,
                        'min'           => 1,
                        'step'          => 7,
                        'max'           => 400,
                        'display_value' => 'text'
                    ),
                    array(
                        'id'        => 'section-log-rotation-end',
                        'type'      => 'section',
                        'indent'    => false // Indent all options below until the next 'section' option is set.
                    ),
                ),
            );
        }

        public function wordpress_default_format()
        {
            $date_format = get_option( 'date_format' );
            $time_format = get_option( 'time_format' );
            $date_format = empty( $date_format ) ? 'F j, Y' : $date_format;
            $time_format = empty( $time_format ) ? 'g:i a' : $time_format;
            return "{$date_format} {$time_format}";
        }

        /**
         * All the possible arguments for Redux.
         * For full documentation on arguments, please refer to: https://github.com/ReduxFramework/ReduxFramework/wiki/Arguments
         * */
        public function setArguments() {

            $theme = wp_get_theme(); // For use with some settings. Not necessary.

            $this->args = array(
                // TYPICAL -> Change these values as you need/desire
                'opt_name'             => 'wpml_settings',
                // This is where your data is stored in the database and also becomes your global variable name.
                'display_name'         => 'WP Mail Logging Settings',
                // Name that appears at the top of your panel
                'display_version'      => $this->plugin_meta['version_installed'],
                // Version that appears at the top of your panel
                'menu_type'            => 'hidden',
                //Specify if the admin menu should appear or not. Options: menu or submenu (Under appearance only)
                'allow_sub_menu'       => false,
                // Show the sections below the admin menu item or not
                //'menu_title'           => 'Settings',
                //'page_title'           => $this->plugin_meta['display_name'],
                // You will need to generate a Google API key to use this feature.
                // Please visit: https://developers.google.com/fonts/docs/developer_api#Auth
                'google_api_key'       => '',
                // Set it you want google fonts to update weekly. A google_api_key value is required.
                'google_update_weekly' => false,
                // Must be defined to add google fonts to the typography module
                'async_typography'     => true,
                // Use a asynchronous font on the front end or font string
                //'disable_google_fonts_link' => true,                    // Disable this in case you want to create your own google fonts loader
                'admin_bar'            => false,
                // Show the panel pages on the admin bar
                'admin_bar_icon'     => 'dashicons-portfolio',
                // Choose an icon for the admin bar menu
                'admin_bar_priority' => 50,
                // Choose an priority for the admin bar menu
                'global_variable'      => '',
                // Set a different name for your global variable other than the opt_name
                'dev_mode'             => false,
                // Show the time the page took to load, etc
                'update_notice'        => true,
                // If dev_mode is enabled, will notify developer of updated versions available in the GitHub Repo
                'customizer'           => false,
                // Enable basic customizer support
                //'open_expanded'     => true,                    // Allow you to start the panel in an expanded way initially.
                //'disable_save_warn' => true,                    // Disable the save warning when a user changes a field

                // OPTIONAL -> Give you extra features
                'page_priority'        => null,
                // Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
                'page_parent'          => 'wpml_plugin_log',
                // For a full list of options, visit: http://codex.wordpress.org/Function_Reference/add_submenu_page#Parameters
                'page_permissions'     => 'manage_options',
                // Permissions needed to access the options panel.
                'menu_icon'            => '',
                // Specify a custom URL to an icon
                'last_tab'             => '',
                // Force your panel to always open to a specific tab (by id)
                'page_icon'            => 'icon-themes',
                // Icon displayed in the admin panel next to your menu_title
                'page_slug'            => 'wpml_plugin_settings',
                // Page slug used to denote the panel
                'save_defaults'        => true,
                // On load save the defaults to DB before user clicks save or not
                'default_show'         => false,
                // If true, shows the default value next to each field that is not the default value.
                'default_mark'         => '*',
                // What to print by the field's title if the value shown is default. Suggested: *
                'show_import_export'   => true,
                // Shows the Import/Export panel when not used as a field.

                // CAREFUL -> These options are for advanced use only
                'transient_time'       => 60 * MINUTE_IN_SECONDS,
                'output'               => true,
                // Global shut-off for dynamic CSS output by the framework. Will also disable google fonts output
                'output_tag'           => true,
                // Allows dynamic CSS to be generated for customizer and google fonts, but stops the dynamic CSS from going to the head
                // 'footer_credit'     => '',                   // Disable the footer credit of Redux. Please leave if you can help it.

                // FUTURE -> Not in use yet, but reserved or partially implemented. Use at your own risk.
                'database'             => '',
                // possible: options, theme_mods, theme_mods_expanded, transient. Not fully functional, warning!
                'system_info'          => false,
                // REMOVE

                // HINTS
                'hints'                => array(
                    'icon'          => 'el el-question-sign',
                    'icon_position' => 'right',
                    'icon_color'    => 'lightgray',
                    'icon_size'     => 'normal',
                    'tip_style'     => array(
                        'color'   => 'light',
                        'shadow'  => true,
                        'rounded' => false,
                        'style'   => 'bootstrap',
                    ),
                    'tip_position'  => array(
                        'my' => 'top left',
                        'at' => 'bottom right',
                    ),
                    'tip_effect'    => array(
                        'show' => array(
                            'effect'   => 'slide',
                            'duration' => '500',
                            'event'    => 'mouseover',
                        ),
                        'hide' => array(
                            'effect'   => 'slide',
                            'duration' => '500',
                            'event'    => 'click mouseleave',
                        ),
                    ),
                )
            );

            // SOCIAL ICONS -> Setup custom links in the footer for quick links in your panel footer icons.
            $this->args['share_icons'][] = array(
                'url'   => 'https://github.com/kgjerstad/wp-mail-logging',
                'title' => 'Visit us on GitHub',
                'icon'  => 'el-icon-github'
                //'img'   => '', // You can use icon OR img. IMG needs to be a full URL.
            );
            $this->args['share_icons'][] = array(
                'url'   => $this->plugin_meta['wp_uri'],
                'title' => 'Visit us on WordPress',
                'icon'  => 'el-icon-wordpress'
            );

            // Add content before the form.
            // $this->args['intro_text'] = __( '<p>This text is displayed above the options panel. It isn\'t required, but more info is always better! The intro_text field accepts all HTML.</p>', 'redux-framework-demo' );

            // Add content after the form.
            // $this->args['footer_text'] = __( '<p>This text is displayed below the options panel. It isn\'t required, but more info is always better! The footer_text field accepts all HTML.</p>', 'redux-framework-demo' );
        }
    }

    global $reduxConfig;
} else {
    echo "The class named Redux_Framework_sample_config has already been called. <strong>Developers, you need to prefix this class with your company name or you'll run into problems!</strong>";
}
