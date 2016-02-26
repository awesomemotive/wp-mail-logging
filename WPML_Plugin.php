<?php

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail as Mail;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

class WPML_Plugin extends WPML_LifeCycle {

	protected $emailLogList;

	const HOOK_LOGGING_COLUMNS = 'wpml_hook_mail_columns';
	const HOOK_LOGGING_COLUMNS_RENDER = 'wpml_hook_mail_columns_render';
	const HOOK_LOGGING_SUPPORTED_FORMATS = 'wpml_hook_supported_formats';
	const HOOK_LOGGING_FORMAT_CONTENT = 'wpml_hook_format_content';

	public static function getTablename( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wpml_' . $name;
	}
	
	public function getPluginDisplayName() {
		return 'WP Mail Logging';
	}

	public function getMainPluginFileName() {
		return 'wp-mail-logging.php';
	}

	public function getVersionSaved() {
		return parent::getVersionSaved();
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
				`receiver` VARCHAR(200) NOT NULL DEFAULT '0',
				`subject` VARCHAR(200) NOT NULL DEFAULT '0',
				`message` TEXT NULL,
				`headers` TEXT NULL,
				`attachments` VARCHAR(800) NOT NULL DEFAULT '0',
				`plugin_version` VARCHAR(200) NOT NULL DEFAULT '0',
				PRIMARY KEY (`mail_id`) 
			) DEFAULT CHARACTER SET = utf8 DEFAULT COLLATE utf8_general_ci;");
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
		global $wpdb;
		$upgradeOk = true;
		$savedVersion = $this->getVersionSaved();
		$tableName = $this->getTablename('mails');

		if ($this->isVersionLessThan($savedVersion, '2.0')) {
			if ($this->isVersionLessThan($savedVersion, '1.2')) {
				$wpdb->query("ALTER TABLE `$tableName` CHANGE COLUMN `to` `receiver` VARCHAR(200)");
			}
			if ($this->isVersionLessThan($savedVersion, '1.3')) {
				$wpdb->query("ALTER TABLE `$tableName` MODIFY COLUMN `attachments` VARCHAR(800) NOT NULL DEFAULT '0'");
			}
			if ($this->isVersionLessThan($savedVersion, '1.4')) {
				$wpdb->query("ALTER TABLE `$tableName` CHARACTER SET utf8 COLLATE utf8_general_ci;");
			}
		}

		if ( !empty( $wpdb->last_error ) ) {
			$upgradeOk = false;
			if ( is_admin() ) {
				echo "There was at least one error while upgrading the database schema. Please report the following error: {$wpdb->last_error}";
			}
		}

		// Post-upgrade, set the current version in the options
		$codeVersion = $this->getVersion();
		if ($upgradeOk && $savedVersion != $codeVersion) {
			$this->saveInstalledVersion();
		}
	}

	public function addActionsAndFilters() {
		// Add options administration page
		// http://plugin.michael-simpson.com/?page_id=47
		add_action( 'admin_menu', array(&$this, 'createSettingsMenu'), 9 );

		// Example adding a script & style just for the options administration page
		// http://plugin.michael-simpson.com/?page_id=47
		//        if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
		//            wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
		//            wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
		//        }


		// Add Actions & Filters
		// http://plugin.michael-simpson.com/?page_id=37
		add_filter( 'plugin_action_links', array( &$this, 'registerPluginActionLinks'), 10, 5 );
		add_filter( 'wp_mail', array( &$this, 'log_email' ), PHP_INT_MAX );
		add_filter( 'set-screen-option', array( &$this, 'save_screen_options' ), 10, 3);
		add_filter( 'wpml_get_plugin_version', array( &$this, 'getVersion' ) );
		add_filter( 'wpml_get_plugin_name', array( &$this, 'getPluginDisplayName' ) );
		add_filter( 'wpml_get_date_time_format', array( &$this, 'getDateTimeFormatString' ) );
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

	private function extractReceiver( $receiver ) {
		return is_array( $receiver ) ? implode( ',\n', $receiver ) : $receiver;
	}

	private function extractHeader( $headers ) {
		return is_array( $headers ) ? implode( ',\n', $headers ) : $headers;
	}

	private function extractAttachments( $attachments ) {
		$attachments = is_array( $attachments ) ? $attachments : array( $attachments );
		$attachment_urls = array();
		$uploads = wp_upload_dir();
		$basename = 'uploads';
		$basename_needle = '/'.$basename.'/';
		foreach ( $attachments as $attachment ) {
    		$append_url = substr( $attachment, strrpos( $attachment, $basename_needle ) + strlen($basename_needle) - 1 );
			$attachment_urls[] = $append_url;
		}
		return implode( ',\n', $attachment_urls );
	}

	private function extractMessage( $mail ) {
		if ( isset($mail['message']) ) {
			// usually the message is stored in the message field
			return $mail['message'];
		} elseif ( isset($mail['html']) ) {
			// for example Mandrill stores the message in the 'html' field (see gh-22)
			return $mail['html'];
		}
		return "";
	}


	private function extractFields( $mail ) {
		return array(
			'receiver'			=> $this->extractReceiver( $mail['to'] ),
			'subject'			=> $mail['subject'],
			'message'			=> $this->extractMessage( $mail ),
			'headers'			=> $this->extractHeader( $mail['headers'] ),
			'attachments'		=> $this->extractAttachments( $mail['attachments'] ),
			'plugin_version'	=> $this->getVersionSaved(),
			'timestamp'         => current_time( 'mysql' )
		);
	}

	public function log_email( $mailOriginal ) {
		// make copy to avoid any changes on the original mail
		$mail = $mailOriginal;

		$fields = $this->extractFields( $mail );
		Mail::create($fields)->save();

		return $mailOriginal;
	}
}
