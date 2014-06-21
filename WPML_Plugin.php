<?php


include_once('WPML_LifeCycle.php');

class WPML_Plugin extends WPML_LifeCycle {

	const HOOK_LOGGING_COLUMNS = 'wpml_hook_mail_columns';
	const HOOK_LOGGING_COLUMNS_RENDER = 'wpml_hook_mail_columns_render';
	

	public static function getTablename( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wpml_' . $name;
	}
	
    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return array of option meta data.
     */
    public function getOptionMetaData() {
        //  http://plugin.michael-simpson.com/?page_id=31
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            //'ATextInput' => array(__('Enter in some text', 'wml')),
            'DeleteOnDeactivation' => array(__('Delete all data on deactivation? (emails and settings)', 'wml'), 'false', 'true'),
            'CanSeeSubmitData' => array(__('Can See Submission data', 'wml'),
                                        'Administrator', 'Editor', 'Author', 'Contributor', 'Subscriber', 'Anyone')
        );
    }

//    protected function getOptionValueI18nString($optionValue) {
//        $i18nValue = parent::getOptionValueI18nString($optionValue);
//        return $i18nValue;
//    }

    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key, $arr[1]);
                }
            }
        }
    }

    public function getPluginDisplayName() {
        return 'WP Mail Logging';
    }

    protected function getMainPluginFileName() {
        return 'wp-mail-logging.php';
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
		global $wpdb;
		$tableName = WPML_Plugin::getTablename('mails');
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
				`mail_id` INT NOT NULL AUTO_INCREMENT,
				`timestamp` TIMESTAMP NOT NULL,
				`to` VARCHAR(200) NOT NULL DEFAULT '0',
				`subject` VARCHAR(200) NOT NULL DEFAULT '0',
				`message` TEXT NULL,
				`headers` TEXT NULL,
				`attachments` TINYINT(1) NOT NULL DEFAULT '0',
				`plugin_version` VARCHAR(200) NOT NULL DEFAULT '0',
				PRIMARY KEY (`mail_id`) 
            );");	
    }
    

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    protected function unInstallDatabaseTables() {
		global $wpdb;
		$tableName = WPML_Plugin::getTablename('mails');
		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");
    }


    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
//     	global $wpdb;
//     	$upgradeOk = true;
//     	$savedVersion = $this->getVersionSaved();
    	
//     	if ($this->isVersionLessThan($savedVersion, '1.0')) {
//     		if ($this->isVersionLessThan($savedVersion, '0.2')) {
//     			$tableName = $this->prefixTableName('mail_logging');
//     			$wpdb->query("ALTER TABLE `$tableName` ADD COLUMN ( `plugin_version` VARCHAR(200) NOT NULL DEFAULT '0')");
//     		}
//     	}
    
//     	// Post-upgrade, set the current version in the options
//     	$codeVersion = $this->getVersion();
//     	if ($upgradeOk && $savedVersion != $codeVersion) {
//     		$this->saveInstalledVersion();
//     	}
    }

    public function addActionsAndFilters() {
		
        // Add options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        add_action('admin_menu', array(&$this, 'createSettingsMenu'));

        // Example adding a script & style just for the options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        //        if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
        //            wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
        //            wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        }


        // Add Actions & Filters
        // http://plugin.michael-simpson.com/?page_id=37
		
         add_filter( 'wp_mail', array(&$this, 'log_email' ) );
			
        // Adding scripts & styles to all pages
        // Examples:
        //        wp_enqueue_script('jquery');
        //        wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));


        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39


        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41

    }

    public function log_email( $mailOriginal ) {
    	$mail = $mailOriginal;
    	global $wpdb;
    	/*
    	[to] => to@example.com
	    [subject] => Test Mail
	    [message] => Test Mail
	
	    [headers] => 
	    [attachments] => Array
	        (
	        )
	    */
    	$to = is_array($mail["to"]) ? implode(",\n", $mail['to']) : $mail['to'];
    	$subject = $mail["subject"];
    	$message = $mail["message"];
    	$headers = is_array($mail["headers"]) ? implode(",\n", $mail['headers']) : $mail['headers'];
    	$hasAttachments = (count ($mail['attachments']) > 0) ? "true" : "false";
    	
    	$tableName = WPML_Plugin::getTablename('mails');
    	$wpdb->insert($tableName, array(
    		'to' => $to,
    		'timestamp' => current_time('mysql'),
    		'subject' => $subject,
    		'message' => $message,
    		'headers' => $headers,
    		'attachments' => $hasAttachments,
    		'plugin_version' => $this->getVersion()
    	));
    	
    	return $mailOriginal;
    }
}
