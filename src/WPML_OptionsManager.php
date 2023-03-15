<?php
/*
	"WordPress Plugin Template" Copyright (C) 2013 Michael Simpson  (email : michael.d.simpson@gmail.com)

	This file is part of WordPress Plugin Template for WordPress.

	WordPress Plugin Template is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	WordPress Plugin Template is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Contact Form to Database Extension.
	If not, see http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace No3x\WPML;

// Exit if accessed directly.
use No3x\WPML\Admin\SettingsTab;
use No3x\WPML\Renderer\WPML_MailRenderer_AJAX_Handler;

if ( ! defined( 'ABSPATH' ) ) exit;

class WPML_OptionsManager {

    protected $supportedMailRendererFormats;
    /** @var WPML_MailRenderer_AJAX_Handler */
    protected $mailRendererAJAXHandler;

    /**
     * Is used to retrive a settings value
     * Important: This implementation understands bool for $default. (unlikely in comparision to all other settings implementation)
     * @since 1.4
     * @param string $settingName The option name to return
     * @param mixed $default (null) The value to return if option not set.
     * @return ambigous <string, mixed> the options value or $default if not found.
     */
    public function getSetting($settingName, $default = null) {
        global $wpml_settings;

        if ( is_null( $wpml_settings ) ) {
            $wpml_settings = SettingsTab::get_settings();
        }

        $retVal = null;
        if (is_array($wpml_settings) && array_key_exists($settingName, $wpml_settings)) {
            $retVal = $wpml_settings[$settingName];
        }
        if (!isset($retVal) && $default !== null) {
            $retVal = $default;
        }
        return $retVal;
    }

    /**
     * Returns the appropriate datetime format string.
     * @since 1.5.0
     * @return string datetime format string
     */
    public function getDateTimeFormatString() {
        // default database like format
        $format = 'Y-m-d G:i:s';
        $date_format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );
        // get option or change to user friendly format as the options maybe not set at all
        $date_format = empty( $date_format ) ? 'F j, Y' : $date_format;
        $time_format = empty( $time_format ) ? 'g:i a' : $time_format;
        if ( $this->getSetting( 'datetimeformat-use-wordpress', false) == true )
            // Overwrite with defined values or default
            $format = $date_format . " " . $time_format;
        return $format;
    }

    public function getOptionNamePrefix() {
        return $this->getClassnameWithoutNamespace() . '_';
    }

    /**
     * Define your options meta data here as an array, where each element in the array
     * @return array of key=>display-name and/or key=>array(display-name, choice1, choice2, ...)
     * key: an option name for the key (this name will be given a prefix when stored in
     * the database to ensure it does not conflict with other plugin options)
     * value: can be one of two things:
     *   (1) string display name for displaying the name of the option to the user on a web page
     *   (2) array where the first element is a display name (as above) and the rest of
     *       the elements are choices of values that the user can select
     * e.g.
     * array(
     *   'item' => 'Item:',             // key => display-name
     *   'rating' => array(             // key => array ( display-name, choice1, choice2, ...)
     *       'CanDoOperationX' => array('Can do Operation X', 'Administrator', 'Editor', 'Author', 'Contributor', 'Subscriber'),
     *       'Rating:', 'Excellent', 'Good', 'Fair', 'Poor')
     */
    public function getOptionMetaData() {
        return array();
    }

    /**
     * @return array of string name of options
     */
    public function getOptionNames() {
        return array_keys($this->getOptionMetaData());
    }

    /**
     * Override this method to initialize options to default values and save to the database with add_option
     * @return void
     */
    protected function initOptions() {
    }

    /**
     * Cleanup: remove all options from the DB
     * @return void
     */
    protected function deleteSavedOptions() {
        $optionMetaData = $this->getOptionMetaData();
        if (is_array($optionMetaData)) {
            foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
                $prefixedOptionName = $this->prefix($aOptionKey); // how it is stored in DB
                delete_option($prefixedOptionName);
            }
        }
    }

    /**
     * Delete the saved settings.
     *
     * @since 1.11.0
     *
     * @return void
     */
    protected function deleteSavedSettings() {

        delete_option( SettingsTab::OPTIONS_NAME );
    }

    /**
     * Delete Product Education related saved option..
     *
     * @since 1.11.0
     *
     * @return void
     */
    protected function deleteSavedProductEducationOptions() {

        delete_option( WPML_ProductEducation::OPTION_KEY );
    }

    /**
     * Cleanup: remove version option
     * @since 1.6.0
     * @return void
     */
    protected function deleteVersionOption() {
        delete_option( $this->prefix( WPML_Plugin::optionVersion ) );
    }

    /**
     * @return string display name of the plugin to show as a name/title in HTML.
     * Just returns the class name. Override this method to return something more readable
     */
    public function getPluginDisplayName() {
        return get_class($this);
    }

    /**
     * @return string slug of the plugin to use as identifier.
     * Just returns the class name in lowercase.
     */
    public function getPluginSlug() {
        return strtolower( $this->getClassnameWithoutNamespace() );
    }

    /**
     * Get the class name without the namespace
     * @return string class name without the namespace.
     * @link http://php.net/manual/de/function.get-class.php#114568
     */
    private function getClassnameWithoutNamespace() {
        $classname = get_class($this);
        if ($pos = strrpos( $classname, '\\')) {
            return substr($classname, $pos + 1);
        }
        return $classname;
    }

    /**
     * Get the prefixed version input $name suitable for storing in WP options
     * Idempotent: if $optionName is already prefixed, it is not prefixed again, it is returned without change
     * @param  $name string option name to prefix. Defined in settings.php and set as keys of $this->optionMetaData
     * @return string
     */
    public function prefix($name) {
        $optionNamePrefix = $this->getOptionNamePrefix();
        if (strpos($name, $optionNamePrefix) === 0) { // 0 but not false
            return $name; // already prefixed
        }
        return $optionNamePrefix . $name;
    }

    /**
     * Remove the prefix from the input $name.
     * Idempotent: If no prefix found, just returns what was input.
     * @param  $name string
     * @return string $optionName without the prefix.
     */
    public function &unPrefix($name) {
        $optionNamePrefix = $this->getOptionNamePrefix();
        if (strpos($name, $optionNamePrefix) === 0) {
            return substr($name, strlen($optionNamePrefix));
        }
        return $name;
    }

    /**
     * A wrapper function delegating to WP get_option() but it prefixes the input $optionName
     * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
     * @param $optionName string defined in settings.php and set as keys of $this->optionMetaData
     * @param $default string default value to return if the option is not set
     * @return string the value from delegated call to get_option(), or optional default value
     * if option is not set.
     */
    public function getOption($optionName, $default = null) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        $retVal = get_option($prefixedOptionName);
        if (!$retVal && $default) {
            $retVal = $default;
        }
        return $retVal;
    }

    /**
     * A wrapper function delegating to WP delete_option() but it prefixes the input $optionName
     * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
     * @param  $optionName string defined in settings.php and set as keys of $this->optionMetaData
     * @return bool from delegated call to delete_option()
     */
    public function deleteOption($optionName) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return delete_option($prefixedOptionName);
    }

    /**
     * A wrapper function delegating to WP add_option() but it prefixes the input $optionName
     * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
     * @param  $optionName string defined in settings.php and set as keys of $this->optionMetaData
     * @param  $value mixed the new value
     * @return null from delegated call to delete_option()
     */
    public function addOption($optionName, $value) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return add_option($prefixedOptionName, $value);
    }

    /**
     * A wrapper function delegating to WP add_option() but it prefixes the input $optionName
     * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
     * @param  $optionName string defined in settings.php and set as keys of $this->optionMetaData
     * @param  $value mixed the new value
     * @return null from delegated call to delete_option()
     */
    public function updateOption($optionName, $value) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return update_option($prefixedOptionName, $value);
    }

    /**
     * A Role Option is an option defined in getOptionMetaData() as a choice of WP standard roles, e.g.
     * 'CanDoOperationX' => array('Can do Operation X', 'Administrator', 'Editor', 'Author', 'Contributor', 'Subscriber')
     * The idea is use an option to indicate what role level a user must minimally have in order to do some operation.
     * So if a Role Option 'CanDoOperationX' is set to 'Editor' then users which role 'Editor' or above should be
     * able to do Operation X.
     * Also see: canUserDoRoleOption()
     * @param  $optionName
     * @return string role name
     */
    public function getRoleOption($optionName) {
        $roleAllowed = $this->getOption($optionName);
        if (!$roleAllowed || $roleAllowed == '') {
            $roleAllowed = 'Administrator';
        }
        return $roleAllowed;
    }

    /**
     * Given a WP role name (case insensitive), return a WP capability which only that role and roles above it have.
     * http://codex.wordpress.org/Roles_and_Capabilities
     * @param  $roleName
     * @return string a WP capability or '' if unknown input role
     */
    protected function roleToCapability($roleName) {
        switch ( ucfirst( $roleName ) ) {
            case 'Super Admin':
                return 'manage_options';
            case 'Administrator':
                return 'manage_options';
            case 'Editor':
                return 'publish_pages';
            case 'Author':
                return 'publish_posts';
            case 'Contributor':
                return 'edit_posts';
            case 'Subscriber':
                return 'read';
            case 'Anyone':
                return 'read';
        }
        return '';
    }

    /**
     * @param $roleName string a standard WP role name like 'Administrator'
     * @return bool
     */
    public function isUserRoleEqualOrBetterThan($roleName) {
        if ('Anyone' == $roleName) {
            return true;
        }
        $capability = $this->roleToCapability($roleName);
        return current_user_can($capability);
    }

    /**
     * @param  $optionName string name of a Role option (see comments in getRoleOption())
     * @return bool indicates if the user has adequate permissions
     */
    public function canUserDoRoleOption($optionName) {
        $roleAllowed = $this->getRoleOption($optionName);
        if ('Anyone' == $roleAllowed) {
            return true;
        }
        return $this->isUserRoleEqualOrBetterThan($roleAllowed);
    }

    /**
     * see: http://codex.wordpress.org/Creating_Options_Pages
     * @return void
     */
    public function createSettingsMenu() {

        global $wp_logging_list_page;

        $pluginNameSlug = $this->getPluginSlug();
        $menu_slug      = $pluginNameSlug . '_log';

        $capability = $this->getSetting( 'can-see-submission-data', 'manage_options' );

        if ( ! empty( $this->getSetting( 'top-level-menu', '1' ) ) ) {
            $this->setup_top_level_menu( $capability, $menu_slug );
        } else {
            // create submenu in the tools menu item
            $wp_logging_list_page = add_submenu_page( 'tools.php', __( 'WP Mail Log', 'wp-mail-logging' ),
                __( 'WP Mail Logging', 'wp-mail-logging' ),
                $capability,
                $menu_slug,
                [ $this, 'LogMenu' ]
            );
        }

        // Add Action to load assets when page is loaded
        add_action( 'load-' . $wp_logging_list_page, [ $this, 'load_assets' ] );

        add_action( 'load-' . $wp_logging_list_page, function() {
            add_screen_option(
                'per_page',
                array(
                    'label' => __('Entries per page', 'wp-mail-logging'),
                    'default' => 25,
                    'option' => 'per_page'
                )
            );
        });
    }

    /**
     * Setup the menu in the top level.
     *
     * @since 1.11.0
     *
     * @param string $capability Capability to be able to access the pages in the menu.
     * @param string $menu_slug  Menu slug.
     *
     * @return void
     */
    private function setup_top_level_menu( $capability, $menu_slug ) {

        global $wp_logging_list_page;

        $wp_logging_list_page = add_menu_page(
            __( 'WP Mail Logging', 'wp-mail-logging' ),
            __( 'WP Mail Logging', 'wp-mail-logging' ),
            $capability,
            $menu_slug,
            [ $this, 'LogMenu' ],
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE4IDkuMjI1ODFDMTggNC44Mzg3MSAxNC40NTE2IDIgMTAgMkM1LjU0ODM5IDIgMiA1LjU4MDY1IDIgMTBDMiAxNC40NTE2IDUuNTQ4MzkgMTggMTAgMThDMTEuNjc3NCAxOCAxMy4zNTQ4IDE3LjQ1MTYgMTQuNzQxOSAxNi40NTE2QzE0LjkwMzIgMTYuMzIyNiAxNC45MzU1IDE2LjA2NDUgMTQuODA2NSAxNS45MDMyTDE0LjI5MDMgMTUuMjkwM0MxNC4xNjEzIDE1LjEyOSAxMy45MzU1IDE1LjEyOSAxMy43NzQyIDE1LjIyNThDMTIuNjc3NCAxNi4wMzIzIDExLjM1NDggMTYuNDUxNiAxMCAxNi40NTE2QzYuNDE5MzUgMTYuNDUxNiAzLjU0ODM5IDEzLjU4MDYgMy41NDgzOSAxMEMzLjU0ODM5IDYuNDUxNjEgNi40MTkzNSAzLjU0ODM5IDEwIDMuNTQ4MzlDMTMuNTE2MSAzLjU0ODM5IDE2LjQ1MTYgNS42Nzc0MiAxNi40NTE2IDkuMjI1ODFDMTYuNDUxNiAxMS4yOTAzIDE1LjA2NDUgMTIuNDE5NCAxMy43NDE5IDEyLjQxOTRDMTMuMTI5IDEyLjQxOTQgMTMuMDk2OCAxMiAxMy4yMjU4IDExLjM4NzFMMTQuMTYxMyA2LjYxMjlDMTQuMTkzNSA2LjM1NDg0IDE0IDYuMTI5MDMgMTMuNzc0MiA2LjEyOTAzSDEyLjUxNjFDMTIuMzIyNiA2LjE2MTI5IDEyLjE2MTMgNi4yOTAzMiAxMi4xMjkgNi40NTE2MUMxMi4wOTY4IDYuNjQ1MTYgMTIuMDY0NSA2Ljc0MTk0IDEyLjA2NDUgNi45MDMyM0MxMS42Nzc0IDYuMjkwMzIgMTAuOTAzMiA1LjkwMzIzIDkuOTY3NzQgNS45MDMyM0M3LjY0NTE2IDUuOTAzMjMgNS42MTI5IDcuOTM1NDggNS42MTI5IDEwLjgzODdDNS42MTI5IDEyLjgwNjUgNi42NDUxNiAxNC4xMjkgOC42MTI5IDE0LjEyOUM5LjU0ODM5IDE0LjEyOSAxMC41ODA2IDEzLjU4MDYgMTEuMTYxMyAxMi43NzQyQzExLjMyMjYgMTMuNzc0MiAxMi4wOTY4IDE0IDEzLjA5NjggMTRDMTYuMjkwMyAxNCAxOCAxMS45MzU1IDE4IDkuMjI1ODFaTTkuMTYxMjkgMTIuMzg3MUM4LjIyNTgxIDEyLjM4NzEgNy42Nzc0MiAxMS43NDE5IDcuNjc3NDIgMTAuNzA5N0M3LjY3NzQyIDguODM4NzEgOC45Njc3NCA3LjY3NzQyIDEwLjA5NjggNy42Nzc0MkMxMS4wNjQ1IDcuNjc3NDIgMTEuNTQ4NCA4LjM4NzEgMTEuNTQ4NCA5LjM1NDg0QzExLjU0ODQgMTAuODcxIDEwLjQ4MzkgMTIuMzg3MSA5LjE2MTI5IDEyLjM4NzFaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K'
        );

        add_submenu_page( $menu_slug,
            __( 'Email Log', 'wp-mail-logging' ),
            __( 'Email Log', 'wp-mail-logging' ),
            $capability,
            $menu_slug
        );

        add_submenu_page( $menu_slug,
            __( 'Settings', 'wp-mail-logging' ),
            __( 'Settings', 'wp-mail-logging' ),
            $capability,
            $menu_slug . '&tab=settings',
            '__return_null'
        );

        add_submenu_page( $menu_slug,
            __( 'SMTP', 'wp-mail-logging' ),
            __( 'SMTP', 'wp-mail-logging' ),
            $capability,
            $menu_slug . '&tab=smtp',
            '__return_null'
        );

        // Fix submenu highlighting depending on the selected tab.
        add_filter( 'submenu_file', function( $submenu_file, $parent_file ) use ( $menu_slug ) {

            if ( $parent_file !== $menu_slug ) {
                return $submenu_file;
            }

            $tab = filter_input( INPUT_GET, 'tab' );

            if ( ! empty( $tab ) ) {
                return $menu_slug . '&tab=' . esc_html( $tab );
            }

            return $submenu_file;
        }, 10, 2 );
    }

    /**
     * Show About page.
     *
     * @since 1.11.0
     *
     * @deprecated 1.11.0
     *
     * @return string
     */
    public function LogSubMenuAbout() {
        return '';
    }

    public function load_assets() {

        global $wp_logging_list_page;

        $screen = get_current_screen();

        if ( $screen->id != $wp_logging_list_page )
            return;

        $assets_url  = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
        $plugin_meta = WPML_Init::getInstance()->getService( 'plugin-meta' );

        if ( empty( $plugin_meta['version'] ) ) {
            return;
        }

        // Enqueue styles and scripts if we're on the list page
        $min = '';

        if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
            $min = '.min';
        }

        wp_enqueue_style(
            'wp-mail-logging-admin',
            $assets_url . "/css/wp-mail-logging-admin{$min}.css",
            [],
            $plugin_meta['version']
        );

        wp_enqueue_style( 'wp-mail-logging-modal', $assets_url . "/css/modal{$min}.css", [], $plugin_meta['version'] );
        wp_enqueue_script('wp-mail-logging-modal', $assets_url . "/js/modal{$min}.js", [ 'jquery' ], $plugin_meta['version'], true);
        wp_localize_script('wp-mail-logging-modal', 'wpml_modal_ajax', $this->mailRendererAJAXHandler->get_ajax_data());
    }

    /**
     * Save Screen option
     * @since 1.3
     */
    function save_screen_options( $status, $option, $value ) {
        if ( 'per_page' == $option ) return $value;
        return $status;
    }

    public function LogMenu() {

        if ( !current_user_can( $this->getSetting( 'can-see-submission-data', 'manage_options' ) ) ) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-mail-logging'));
        }

        if (!class_exists( 'Email_Log_List_Table' ) ) {
            require_once ( plugin_dir_path( __FILE__ ) . 'WPML_Email_Log_List.php' );
        }

        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : null;
        ?>
        <div id="wp-mail-logging" class="wrap">
            <div class="wp-mail-logging-page-content">
                <?php
                /**
                 * Hook the tab content here.
                 *
                 * @since 1.11.0
                 *
                 * @param string $tab Current active tab.
                 */
                do_action( 'wp_mail_logging_admin_tab_content', $tab );
                ?>
            </div>
        </div>
        <?php
    }

    public function _LogMenu() {

        if ( ! current_user_can( $this->getSetting( 'can-see-submission-data', 'manage_options' ) ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-mail-logging' ) );
        }

        if ( ! class_exists( 'Email_Log_List_Table' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . 'WPML_Email_Log_List.php' );
        }
        ?>
            <form id="email-list" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <?php
                wp_nonce_field( WPML_Email_Log_List::NONCE_LIST_TABLE, WPML_Email_Log_List::NONCE_LIST_TABLE . '_nonce' );
                $search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;
                /** @var WPML_Email_Log_List $emailLogList */
                $emailLogList = WPML_Init::getInstance()->getService('emailLogList');
                $emailLogList->prepare_items( $search );
                $emailLogList->search_box( __( 'Search' ), 's' );
                $emailLogList->views();
                $emailLogList->display();
                ?>
            </form>
        <?php
    }

    /**
     * Override this method and follow its format.
     * The purpose of this method is to provide i18n display strings for the values of options.
     * For example, you may create a options with values 'true' or 'false'.
     * In the options page, this will show as a drop down list with these choices.
     * But when the the language is not English, you would like to display different strings
     * for 'true' and 'false' while still keeping the value of that option that is actually saved in
     * the DB as 'true' or 'false'.
     * To do this, follow the convention of defining option values in getOptionMetaData() as canonical names
     * (what you want them to literally be, like 'true') and then add each one to the switch statement in this
     * function, returning the "__()" i18n name of that string.
     * @param  $optionValue string
     * @return string __($optionValue) if it is listed in this method, otherwise just returns $optionValue
     */
    protected function getOptionValueI18nString($optionValue) {
        switch ($optionValue) {
            case 'true':
                return __('true', 'wp-mail-logging');
            case 'false':
                return __('false', 'wp-mail-logging');

            case 'Administrator':
                return __('Administrator', 'wp-mail-logging');
            case 'Editor':
                return __('Editor', 'wp-mail-logging');
            case 'Author':
                return __('Author', 'wp-mail-logging');
            case 'Contributor':
                return __('Contributor', 'wp-mail-logging');
            case 'Subscriber':
                return __('Subscriber', 'wp-mail-logging');
            case 'Anyone':
                return __('Anyone', 'wp-mail-logging');
        }
        return $optionValue;
    }

    /**
     * Query MySQL DB for its version
     * @return string|false
     */
    protected function getMySqlVersion() {
        global $wpdb;
        $rows = $wpdb->get_results('select version() as mysqlversion');
        if (!empty($rows)) {
            return $rows[0]->mysqlversion;
        }
        return false;
    }

    /**
     * If you want to generate an email address like "no-reply@your-site.com" then
     * you can use this to get the domain name part.
     * E.g.  'no-reply@' . $this->getEmailDomain();
     * This code was stolen from the wp_mail function, where it generates a default
     * from "wordpress@your-site.com"
     * @return string domain name
     */
    public function getEmailDomain() {
        // Get the site domain and get rid of www.
        $sitename = strtolower($_SERVER['SERVER_NAME']);
        if (substr($sitename, 0, 4) == 'www.') {
            $sitename = substr($sitename, 4);
        }
        return $sitename;
    }
}

