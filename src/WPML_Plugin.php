<?php

namespace No3x\WPML;

use No3x\WPML\Admin\EmailLogsTab;
use No3x\WPML\Admin\SettingsTab;
use No3x\WPML\Admin\SMTPTab;
use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Renderer\WPML_MailRenderer_AJAX_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

class WPML_Plugin extends WPML_LifeCycle implements IHooks {

    const HOOK_LOGGING_MAIL = 'log_email';
    const HOOK_LOGGING_MAIL_PRIORITY = PHP_INT_MAX;
    const HOOK_LOGGING_SUPPORTED_FORMATS = 'wpml_hook_supported_formats';

    /**
     * WPML_Plugin constructor.
     * @param $supportedMailRendererFormats
     * @param WPML_MailRenderer_AJAX_Handler $mailRendererAJAXHandler
     */
    public function __construct($supportedMailRendererFormats, $mailRendererAJAXHandler) {
        $this->supportedMailRendererFormats = $supportedMailRendererFormats;
        $this->mailRendererAJAXHandler = $mailRendererAJAXHandler;
    }

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
				`host` VARCHAR(200) NOT NULL DEFAULT '0',
				`receiver` VARCHAR(200) NOT NULL DEFAULT '0',
				`subject` VARCHAR(200) NOT NULL DEFAULT '0',
				`message` TEXT NULL,
				`headers` TEXT NULL,
				`attachments` VARCHAR(800) NOT NULL DEFAULT '0',
				`error` VARCHAR(400) NULL DEFAULT '',
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
        // Remove the cache option indicating tables are installed
        wp_cache_delete(parent::CACHE_INSTALLED_KEY, parent::CACHE_GROUP);
    }

    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
        global $wpdb;

        $savedVersion = $this->getVersionSaved();
        if(! $this->isInstalled() || empty( $savedVersion ) ) {
            // The plugin must be installed before any upgrades
            return;
        }

        $upgradeOk = true;
        $savedVersion = $this->getVersionSaved();
        $codeVersion = $this->getVersion();
        $tableName = $this->getTablename('mails');

        /* check for downgrade or beta
        if( $this->isVersionLessThan($codeVersion, $savedVersion)
            ||  false !== strpos($savedVersion, 'beta') ) {
            $upgradeOk = false;
            // This is only be the case if the user had a beta version installed
            if ( is_admin() ) {
                wp_die( "[{$this->getPluginDisplayName()}] You have installed version {$savedVersion} but try to install {$codeVersion}! This would require a database downgrade which is not supported! You need to install {$savedVersion} again, enable \"Cleanup\" in the settings and disable the plugin.");
            }
        }*/

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
            if ($this->isVersionLessThan($savedVersion, '1.7')) {
                $wpdb->query("ALTER TABLE `$tableName` ADD COLUMN `host` VARCHAR(200) NOT NULL DEFAULT '0' AFTER `timestamp`;");
            }
            if ($this->isVersionLessThan($savedVersion, '1.8')) {
                // Due to upgrade bug upgrades from 1.6.2 to 1.7.0 failed. Redo the schema change if required
                $results = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$tableName` LIKE %s", 'host' ) );
                $column_exists = ( count( $results ) > 0 ) ? true : false;

                if ( false === $column_exists && is_array( $results ) ) {
                    $wpdb->query("ALTER TABLE `$tableName` ADD COLUMN `host` VARCHAR(200) NOT NULL DEFAULT '0' AFTER `timestamp`;");
                }

                $wpdb->query("ALTER TABLE `$tableName` ADD COLUMN `error` VARCHAR(400) NULL DEFAULT '' AFTER `attachments`;");
            }
        }

        if ( !empty( $wpdb->last_error ) ) {
            $upgradeOk = false;
            if ( is_admin() ) {
                echo "There was at least one error while upgrading the database schema. Please report the following error: {$wpdb->last_error}";
            }
        }

        // Post-upgrade, set the current version in the options
        if ($upgradeOk && $savedVersion != $codeVersion) {
            $this->saveInstalledVersion();
        }
    }

    public function addActionsAndFilters() {

        EmailLogsTab::get_instance()->hooks();
        SettingsTab::get_instance()->hooks();
        SMTPTab::get_instance()->hooks();

        add_action( 'current_screen', [ $this, 'create_screens' ], 90 );

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
        add_filter( 'wp_mail', array( $this, self::HOOK_LOGGING_MAIL ), self::HOOK_LOGGING_MAIL_PRIORITY );
        add_action( 'wp_mail_failed', array( &$this, 'log_email_failed' ) );
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

        // Admin footer text.
        add_filter( 'admin_footer_text', [ $this, 'admin_footer' ], 1, 2 );

        add_filter( 'in_admin_header', [ $this, 'admin_header' ] );
    }

    /**
     * Header for WP Mail Logging admin pages.
     *
     * @since 1.11.0
     *
     * @return void
     *
     * @throws \Exception
     */
    public function admin_header() {

        global $wp_logging_list_page;

        $current_screen = get_current_screen();

        if ( $current_screen->id !== $wp_logging_list_page ) {
            return;
        }

        $assets_url = WPML_Init::getInstance()->getService( 'plugin' )->get_assets_url();
        $tab        = isset( $_GET['tab'] ) ? $_GET['tab'] : null;
        ?>
        <div id="wp-mail-logging-page-header-temp"></div>
        <div id="wp-mail-logging-page-header">
            <div class="wp-mail-logging-page-title">
                <div class="wp-mail-logging-logo-image">
                    <?php
                    printf(
                        '<img src="%1$s" srcset="%2$s 2x" alt="%3$s"/>',
                        esc_url( $assets_url . '/images/logo.png' ),
                        esc_url( $assets_url . '/images/logo@2x.png' ),
                        esc_html__( 'WP Mail Logging logo')
                    )
                    ?>
                </div>

                <div class="wp-mail-logging-logo-sep">
                    <img src="<?php echo esc_url( $assets_url . '/images/sep.png' ); ?>" />
                </div>

                <?php
                $admin_page_url = WPML_Utils::get_admin_page_url();
                $menu_tabs      = [
                    [
                        'slug'  => '',
                        'label' => __( 'Email Log', 'wp-mail-logging' ),
                    ],
                    [
                        'slug'  => 'settings',
                        'label' => __( 'Settings', 'wp-mail-logging' ),
                    ],
                    [
                        'slug'  => 'smtp',
                        'label' => __( 'SMTP', 'wp-mail-logging' ),
                    ],
                ];

                foreach ( $menu_tabs as $menu_tab ) {
                    ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $menu_tab['slug'], $admin_page_url ) ) ?>"
                       class="tab <?php echo $tab == $menu_tab['slug'] ? 'active' : null ?>"
                       aria-current="page">
                        <?php echo esc_html( $menu_tab['label'] ) ?>
                    </a>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Create WP Mail Logging screens.
     *
     * @since 1.11.0
     *
     * @param \WP_Screen $current_screen
     *
     * @return void
     */
    public function create_screens( $current_screen ) {

        global $wp_logging_list_page;

        if ( $current_screen->id !== $wp_logging_list_page ) {
            return;
        }

        $tab = filter_input( INPUT_GET, 'tab' );

        switch ( $tab ) {
            case 'settings':
                $tabObj = SettingsTab::get_instance();
                break;
            case 'smtp':
                $tabObj = SMTPTab::get_instance();
                break;
            default:
                $tabObj = EmailLogsTab::get_instance();
                break;
        }

        if ( is_null( $tabObj ) ) {
            return;
        }

        $tabObj->screen_hooks();
    }

    /**
     * Action to log errors for mails failed to send.
     *
     * @since 1.8.0
     * @global $wpml_current_mail_id
     * @param \WP_Error $wperror
     */
    public function log_email_failed( $wperror ) {
        global $wpml_current_mail_id;
        if(!isset($wpml_current_mail_id)) return;
        $failed_mail = Mail::find_one($wpml_current_mail_id);
        if( !$failed_mail ) return;
        $failed_mail->set_error($wperror->get_error_message())->save();
    }

    /**
     * Logs mail to database.
     *
     * @param array $mailArray
     * @global $wpml_current_mail_id
     * @since 1.0
     * @return array $mailOriginal
     */
    public function log_email( $mailArray ) {
        global $wpml_current_mail_id;

        $mail = (new WPML_MailExtractor())->extract($mailArray);
        $mail->set_plugin_version($this->getVersionSaved());
        $mail->set_timestamp(current_time( 'mysql' ));
        $mail->set_host( isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '');

        $wpml_current_mail_id = $mail->save();

        return $mailArray;
    }

    public static function getClass() {
        return __CLASS__;
    }

    /**
     * When user is on a WP Mail Logging related admin page, display footer text
     * that graciously asks them to rate us.
     *
     * @since 1.11.0
     *
     * @param string $text Footer text.
     *
     * @return string
     */
    public function admin_footer( $text ) {

        global $current_screen, $wp_logging_list_page;

        if ( empty( $current_screen->id ) || $current_screen->id !== $wp_logging_list_page ) {
            return $text;
        }

        $url  = 'https://wordpress.org/support/plugin/wp-mail-logging/reviews/?filter=5#new-post';

        return sprintf(
            wp_kses( /* translators: $1$s - WP Mail Logging plugin name; $2$s - WP.org review link; $3$s - WP.org review link. */
                __( 'Please rate %1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%3$s" target="_blank" rel="noopener">WordPress.org</a> to help us spread the word.', 'wp-mail-logging' ),
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                        'rel'    => [],
                    ],
                ]
            ),
            '<strong>WP Mail Logging</strong>',
            $url,
            $url
        );
    }

    /**
     * Plugin activation hook.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function activate() {}

    /**
     * Get the assets URL.
     *
     * @since 1.11.0
     *
     * @return string
     */
    public function get_assets_url() {

        return untrailingslashit( WP_MAIL_LOGGING_PLUGIN_URL ) . '/assets';
    }
}
