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
if ( ! defined( 'ABSPATH' ) ) exit;

class WPML_LifeCycle extends WPML_InstallIndicator {

    public function install() {

        // Initialize Plugin Options
        $this->initOptions();

        // Initialize DB Tables used by the plugin
        $this->installDatabaseTables();

        // Other Plugin initialization - for the plugin writer to override as needed
        $this->otherInstall();

        // Record the installed version
        $this->saveInstalledVersion();

    }

    public function uninstall() {
        $this->otherUninstall();

        if ( $this->getSetting('delete-on-deactivation', false) == true ) {

            // Delete some options.
            delete_option( 'wp_mail_logging_activated_time' );
            delete_option( 'wp_mail_logging_user_feedback_notice' );
            delete_option( 'wp_mail_logging_db_version' );

            // Remove some transient.
            delete_transient( self::CACHE_GROUP . '_' . self::CACHE_INSTALLED_KEY );

            //TOOD: is multi site?
            $this->unInstallDatabaseTables();
            $this->deleteSavedOptions();
            $this->deleteVersionOption();

            $this->deleteSavedSettings();
            $this->deleteSavedProductEducationOptions();
        }
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=105
     * @return void
     */
    public function deactivate() {
        $this->uninstall();
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return void
     */
    protected function initOptions() {
    }

    public function addActionsAndFilters() {
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Called by install() to create any database tables if needed.
     * Best Practice:
     * (1) Prefix all table names with $wpdb->prefix
     * (2) make table names lower case only
     * @return void
     */
    protected function installDatabaseTables() {
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    protected function unInstallDatabaseTables() {
    }

    /**
     * Override to add any additional actions to be done at install time
     * See: http://plugin.michael-simpson.com/?page_id=33
     * @return void
     */
    protected function otherInstall() {
    }

    /**
     * Override to add any additional actions to be done at uninstall time
     * See: http://plugin.michael-simpson.com/?page_id=33
     * @return void
     */
    protected function otherUninstall() {
    }

    /**
     * Puts the configuration page in the Plugins menu by default.
     * Override to put it elsewhere or create a set of submenus
     * Override with an empty implementation if you don't want a configuration page
     * @return void
     */
    public function addSettingsSubMenuPage() {
        $this->addSettingsSubMenuPageToPluginsMenu();
        //$this->addSettingsSubMenuPageToSettingsMenu();
    }


    protected function requireExtraPluginFiles() {
        require_once(ABSPATH . 'wp-includes/pluggable.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    /**
     * @return string Slug name for the URL to the Setting page
     * (i.e. the page for setting options)
     */
    protected function getSettingsSlug() {
        return get_class($this) . 'Settings';
    }

    protected function addSettingsSubMenuPageToPluginsMenu() {
        $this->requireExtraPluginFiles();
        $displayName = $this->getPluginDisplayName();
        add_submenu_page('plugins.php',
            $displayName,
            $displayName,
            'manage_options',
            $this->getSettingsSlug(),
            array(&$this, 'settingsPage'));
    }


    protected function addSettingsSubMenuPageToSettingsMenu() {
        $this->requireExtraPluginFiles();
        $displayName = $this->getPluginDisplayName();
        add_options_page($displayName,
            $displayName,
            'manage_options',
            $this->getSettingsSlug(),
            array(&$this, 'settingsPage'));
    }

    /**
     * @param  $name string name of a database table
     * @return string input prefixed with the WordPress DB table prefix
     * plus the prefix for this plugin (lower-cased) to avoid table name collisions.
     * The plugin prefix is lower-cases as a best practice that all DB table names are lower case to
     * avoid issues on some platforms
     */
    protected function prefixTableName($name) {
        global $wpdb;
        return $wpdb->prefix .  strtolower($this->prefix($name));
    }


    /**
     * Convenience function for creating AJAX URLs.
     *
     * @param $actionName string the name of the ajax action registered in a call like
     * add_action('wp_ajax_actionName', array(&$this, 'functionName'));
     *     and/or
     * add_action('wp_ajax_nopriv_actionName', array(&$this, 'functionName'));
     *
     * If have an additional parameters to add to the Ajax call, e.g. an "id" parameter,
     * you could call this function and append to the returned string like:
     *    $url = $this->getAjaxUrl('myaction&id=') . urlencode($id);
     * or more complex:
     *    $url = sprintf($this->getAjaxUrl('myaction&id=%s&var2=%s&var3=%s'), urlencode($id), urlencode($var2), urlencode($var3));
     *
     * @return string URL that can be used in a web page to make an Ajax call to $this->functionName
     */
    public function getAjaxUrl($actionName) {
        return admin_url('admin-ajax.php') . '?action=' . $actionName;
    }

    public function registerPluginActionLinks( $actions, $plugin_file ) {
        if ($this->getMainPluginFileName() == basename($plugin_file)) {
            $admin_url = add_query_arg( 'tab', 'settings', WPML_Utils::get_admin_page_url() );
            $settings  = array('settings' => '<a href="' . esc_url( $admin_url ) . '">' . __( 'Settings', 'wp-mail-logging' ) . '</a>' );

            if ( ! is_array( $actions ) ) {
                $actions = [];
            }

            $actions = array_merge( $settings, $actions );
        }
        return $actions;
    }
}
