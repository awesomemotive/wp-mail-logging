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

class WPML_OptionsManager {

    public function getOptionNamePrefix() {
        return get_class($this) . '_';
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
     * @return string display name of the plugin to show as a name/title in HTML.
     * Just returns the class name. Override this method to return something more readable
     */
    public function getPluginDisplayName() {
        return get_class($this);
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
     * Given a WP role name, return a WP capability which only that role and roles above it have
     * http://codex.wordpress.org/Roles_and_Capabilities
     * @param  $roleName
     * @return string a WP capability or '' if unknown input role
     */
    protected function roleToCapability($roleName) {
        switch ($roleName) {
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

	    global $wp_version;
	    global $wp_logging_list_page;

	    $pluginIcon = '';
	    if ( $wp_version >= 3.8 ) $pluginIcon = 'dashicons-email-alt';

        $pluginName = $this->getPluginDisplayName();
        //create new top-level menu
        $wp_logging_list_page = add_menu_page(__('WP Mail Log', 'wpml'),
									          __('WP Mail Log', 'wpml'),
						                      'administrator',
						                      get_class($this) . '_log',
						                      array(&$this, 'LogMenu'),
							                  $pluginIcon
						        );

	    // Add Action to load assets when page is loaded
	    add_action( 'load-' . $wp_logging_list_page, array( $this, 'load_assets' ) );

        //call register settings function
        add_action('admin_init', array(&$this, 'registerSettings'));
        
        add_submenu_page(get_class($this) . '_log', 
				       	__('Settings', 'wpml'), 
				        __('Settings', 'wpml'), 
				        'administrator',  
		        		get_class($this) . '_settings', 
						array(&$this, 'LogSubMenuSettings') );
        
        add_action( "contextual_help", array( &$this, 'create_settings_panel' ), 10, 3 );
    }

	public function load_assets() {

		global $wp_logging_list_page;
		$screen = get_current_screen();

		if ( $screen->id != $wp_logging_list_page )
			return;

		// Enqueue Styles and Scripts if we're on the list page
		wp_enqueue_script( 'wp-logging-modal-js', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/js/modal.js', array( 'jquery' ), '1.0.0', TRUE );
		wp_enqueue_style( 'wp-logging-modal-css', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/css/modal.css', array(), '1.0.0' );

	}

    public function registerSettings() {
        $settingsGroup = get_class($this) . '-settings-group';
        $optionMetaData = $this->getOptionMetaData();
        foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
            register_setting($settingsGroup, $aOptionMeta);
        }
    }
    
    /**
     * Add settings Panel
     */
    function create_settings_panel($contextual_help, $screen_id, $screen) {
    	
    	global $hook_suffix;
    	
    	// Just add if we are at the plugin page
    	if( strpos($hook_suffix, get_class($this) . '_log' ) == false )
    		return $contextual_help;
    	
    	// The add_help_tab function for screen was introduced in WordPress 3.3.
    	if ( ! method_exists( $screen, 'add_help_tab' ) )
    		return $contextual_help;
    	
    	
    	// List screen properties
    	$left = '<div style="width:50%;float:left;">' 
    			. '<h4>About this plugin</h4>'
    			. '<p>This plugin is open source.</p>'
    			. '</div>';
    	
    	
    	$right = '<div style="width:50%;float:right;">'
    			. '<h4>Donate</h4>' 
    			. '<p>If you like the plugin please consider to make a donation. More information are provided on my <a href="http://no3x.de/web/donate">website</a>.</p>'
    			. '</div>';
    	
    	$help_content = $left . $right;
    	
    	/**
    	 * Content specified inline
    	*/
    	$screen->add_help_tab(
    			array(
    					'title'    => __('About Plugin', 'wpml'),
    					'id'       => 'about_tab',
    					'content'  => '<p>' . __( "{$this->getPluginDisplayName()}, logs each email sent by WordPress.", 'wpml') . '</p>' . $help_content,
    					'callback' => false
    			)
    	);
    
    	// Add help sidebar
    	$screen->set_help_sidebar(
    			'<p><strong>' . __('More information', 'wpml') . '</strong></p>' .
    			'<p><a href = "http://wordpress.org/extend/plugins/wp-mail-logging/">' . __('Plugin Homepage/support', 'wpml') . '</a></p>' .
    			'<p><a href = "http://no3x.de/">' . __("Plugin author's blog", 'wpml') . '</a></p>'
    	);
    	
    	// Add screen options
    	$screen->add_option(
    			'per_page',
    			array(
    					'label' => __('Entries per page', 'wpml'),
    					'default' => 25,
    					'option' => 'per_page'
    			)
    	);
    	
    	return $contextual_help;
    }
    
    public function LogMenu() {

	    global $wp_version;

    	if (!current_user_can('manage_options')) {
    		wp_die(__('You do not have sufficient permissions to access this page.', 'wpml'));
    	}
    	
    	if (!class_exists( 'Email_Log_List_Table' ) ) {
    		require_once ( plugin_dir_path( __FILE__ ) . 'WPML_Email_Log_List.php' );
    	}

    	?>
    	 <div class="wrap">
            <h2><?php echo $this->getPluginDisplayName(); echo ' '; _e('Log', 'wpml'); ?></h2>
				<?php
				$emailLoggingListTable = new Email_Logging_ListTable();
				$emailLoggingListTable->prepare_items();
				?>

				<div id="wp-mail-logging-modal-wrap">
					<div id="wp-mail-logging-modal-backdrop"></div>
					<div id="wp-mail-logging-modal-content-wrap">
						<div id="wp-mail-logging-modal-content">
							<div id="wp-mail-logging-modal-content-header">
								<a id="wp-mail-logging-modal-content-header-close" class="wp-mail-logging-modal-close" href="#" title="Close">
									<?php if ( $wp_version >= 3.8 ): ?>
										<div class="dashicons dashicons-no"></div>
									<?php else: ?>
										<span class="wp-mail-logging-modal-content-header-compat-close">X</span>
									<?php endif; ?>
								</a>
								<?php if ( $wp_version >= 3.8 ): ?>
									<div id="wp-mail-logging-modal-content-header-icon" class="dashicons dashicons-email-alt"></div>
								<?php endif; ?>
								<div id="wp-mail-logging-modal-content-header-title">
									<?php _e( 'Message', 'wpml' ); ?>
								</div>
							</div>
							<div id="wp-mail-logging-modal-content-body">
								<div id="wp-mail-logging-modal-content-body-content">

								</div>
							</div>
							<div id="wp-mail-logging-modal-content-footer">
								<a class="wp-mail-logging-modal-close button button-primary" href="#"><?php _e( 'Close', 'wpml' ); ?></a>
							</div>
						</div>
					</div>
				</div>

				<form id="email-list" method="get">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<?php
					$emailLoggingListTable->display();
					?>
				</form>
				
            
		</div> 
    	<?php
    }	

    /**
     * Creates HTML for the Administration page to set options for this plugin.
     * Override this method to create a customized page.
     * @return void
     */
    public function LogSubMenuSettings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpml'));
        }

        $optionMetaData = $this->getOptionMetaData();

        // Save Posted Options
        if ($optionMetaData != null) {
            foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
                if (isset($_POST[$aOptionKey])) {
                    $this->updateOption($aOptionKey, $_POST[$aOptionKey]);
                }
            }
        }

        // HTML for the page
        $settingsGroup = get_class($this) . '-settings-group';
        ?>
        <div class="wrap">

            <h2><?php echo $this->getPluginDisplayName(); echo ' '; _e('Settings', 'wpml'); ?></h2>

            <form method="post" action="">
            <?php settings_fields($settingsGroup); ?>
                <table class="form-table"><tbody>
                <?php
                if ($optionMetaData != null) {
                    foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
                        $displayText = is_array($aOptionMeta) ? $aOptionMeta[0] : $aOptionMeta;
                        ?>
                            <tr valign="top">
                                <th scope="row"><p><label for="<?php echo $aOptionKey ?>"><?php echo $displayText ?></label></p></th>
                                <td>
                                <?php $this->createFormControl($aOptionKey, $aOptionMeta, $this->getOption($aOptionKey)); ?>
                                </td>
                            </tr>
                        <?php
                    }
                }
                ?>
                </tbody></table>
                <p class="submit">
                    <input type="submit" class="button-primary"
                           value="<?php _e('Save Changes', 'wpml') ?>"/>
                </p>
            </form>
        </div>
        <?php

    }

    /**
     * Helper-function outputs the correct form element (input tag, select tag) for the given item
     * @param  $aOptionKey string name of the option (un-prefixed)
     * @param  $aOptionMeta mixed meta-data for $aOptionKey (either a string display-name or an array(display-name, option1, option2, ...)
     * @param  $savedOptionValue string current value for $aOptionKey
     * @return void
     */
    protected function createFormControl($aOptionKey, $aOptionMeta, $savedOptionValue) {
        if (is_array($aOptionMeta) && count($aOptionMeta) >= 2) { // Drop-down list
            $choices = array_slice($aOptionMeta, 1);
            ?>
            <p><select name="<?php echo $aOptionKey ?>" id="<?php echo $aOptionKey ?>">
            <?php
                            foreach ($choices as $aChoice) {
                $selected = ($aChoice == $savedOptionValue) ? 'selected' : '';
                ?>
                    <option value="<?php echo $aChoice ?>" <?php echo $selected ?>><?php echo $this->getOptionValueI18nString($aChoice) ?></option>
                <?php
            }
            ?>
            </select></p>
            <?php

        }
        else { // Simple input field
            ?>
            <p><input type="text" name="<?php echo $aOptionKey ?>" id="<?php echo $aOptionKey ?>"
                      value="<?php echo esc_attr($savedOptionValue) ?>" size="50"/></p>
            <?php

        }
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
                return __('true', 'wpml');
            case 'false':
                return __('false', 'wpml');

            case 'Administrator':
                return __('Administrator', 'wpml');
            case 'Editor':
                return __('Editor', 'wpml');
            case 'Author':
                return __('Author', 'wpml');
            case 'Contributor':
                return __('Contributor', 'wpml');
            case 'Subscriber':
                return __('Subscriber', 'wpml');
            case 'Anyone':
                return __('Anyone', 'wpml');
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

