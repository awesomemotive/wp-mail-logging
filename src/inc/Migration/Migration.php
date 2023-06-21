<?php

namespace No3x\WPML\Migration;

use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\WPML_Plugin;
use No3x\WPML\WPML_Utils;

class Migration {

    /**
     * Version of the latest migration.
     *
     * @since 1.12.0
     *
     * @var int
     */
    const VERSION = 2;

    /**
     * Option key where we save the current DB version.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const OPTION_NAME = 'wp_mail_logging_db_version';

    /**
     * Nonce for migration.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const MIGRATION_NONCE = 'wp_mail_logging_migration_nonce';

    /**
     * Nonce for migration notice dismiss.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const MIGRATION_NOTICE_DISMISS_NONCE = 'wp_mail_logging_migration_dismiss_nonce';

    /**
     * DB key for migration notice dismiss.
     *
     * This will only exists in DB option if the user has dismissed the notice.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const MIGRATION_NOTICE_DISMISS_DB_KEY = 'wp_mail_logging_migration_dismiss_notice';

    /**
     * DB key for migration admin notice dismiss.
     *
     * This will only exists in DB option if the user has dismissed the admin notice.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const MIGRATION_ADMIN_NOTICE_DISMISS_DB_KEY = 'wp_mail_logging_migration_dismiss_admin_notice';

    /**
     * Current migration version.
     *
     * @since 1.12.0
     *
     * @var int
     */
    private $current_version;

    /**
     * Flag to indicate if a migration is needed.
     *
     * @since 1.12.0
     *
     * @var bool
     */
    private $is_migration_needed = null;

    /**
     * Current migration's error.
     *
     * @since 1.12.0
     *
     * @var string
     */
    private $error;

    /**
     * Whether the migration was successful.
     *
     * @since 1.12.0
     *
     * @var bool
     */
    private $is_success = false;

    /**
     * Constructor
     *
     * @since 1.12.0
     */
    public function __construct() {


        $this->hooks();
    }

    /**
     * WP Hooks.
     *
     * @since 1.12.0
     *
     * @return void
     */
    private function hooks() {

        add_action( 'current_screen', [ $this, 'init'] );
        add_action( 'admin_notices', [ $this, 'display_migration_notice' ] );
        add_action( 'admin_notices', [ $this, 'display_migration_result' ] );
        add_action( 'wp_mail_logging_admin_tab_content_before', [ $this, 'display_migration_section'] );
        add_action( 'wp_ajax_wp_mail_logging_dismiss_db_upgrade_notice', [ $this, 'ajax_dismiss_migration_notice' ] );
        add_filter( 'wp_mail_logging_jquery_confirm_localized_strings', [ $this, 'jquery_confirm_localized_string' ] );
    }

    /**
     * Init the migration UI and process if requested.
     *
     * @since 1.12.0
     *
     * @return void
     */
    public function init() {

        if (
            ! $this->is_wp_mail_logging_admin_page() ||
            ! $this->is_migration_needed()
        ) {
            return;
        }

        // Check if migration is requested.
        if ( ! empty( $_GET['migration'] ) && check_admin_referer( self::MIGRATION_NONCE, 'nonce' ) && current_user_can( 'manage_options' ) ) {
            $this->run( self::VERSION );
        }
    }

    /**
     * Whether we are WP Mail Logging admin pages.
     *
     * @since 1.12.0
     *
     * @return bool
     */
    private function is_wp_mail_logging_admin_page() {

        global $wp_logging_list_page;

        $current_screen = get_current_screen();

        return $current_screen->id === $wp_logging_list_page;
    }

    /**
     * Whether or not the migration is needed.
     *
     * @since 1.12.0
     *
     * @return bool
     */
    private function is_migration_needed() {

        if ( ! is_null( $this->is_migration_needed ) ) {
            return $this->is_migration_needed;
        }

        if (
            version_compare( $this->get_current_version(), self::VERSION, '<' ) &&
            ! get_option( self::MIGRATION_NOTICE_DISMISS_DB_KEY, false )
        ) {
            $this->is_migration_needed = true;
        } else {
            $this->is_migration_needed = false;
        }

        return $this->is_migration_needed;
    }

    /**
     * Get current DB version.
     *
     * @since 1.12.0
     *
     * @return int
     */
    private function get_current_version() {

        if ( is_null( $this->current_version ) ) {

            $this->current_version = (int) get_option( self::OPTION_NAME, 0 );
        }

        return $this->current_version;
    }

    /**
     * AJAX handler when DB upgrade notice is dismissed.
     *
     * @since 1.12.0
     *
     * @return void
     */
    public function ajax_dismiss_migration_notice() {

        if (
            empty( $_POST['nonce'] ) ||
            ! check_admin_referer( self::MIGRATION_NOTICE_DISMISS_NONCE, 'nonce' ) ||
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error();
        }

        if ( ! empty( $_POST['type'] ) && $_POST['type'] === 'admin-notice' ) {
            update_option( self::MIGRATION_ADMIN_NOTICE_DISMISS_DB_KEY, true, false );
        } else {
            $this->dismiss_db_upgrade_banner();
        }

        wp_send_json_success();
    }

    /**
     * Dismiss the DB upgrade banner.
     *
     * Check if the user already performed migration's 1 and 2 manually
     * and if so, add the option that tracks the completed migration.
     *
     * @since 1.12.0
     *
     * @return void
     */
    private function dismiss_db_upgrade_banner() {

        global $wpdb;

        // Get the existing charset of the table.
        $charset = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT CHARACTER_SET_NAME
                FROM information_schema.columns
                WHERE TABLE_NAME = %s
                AND COLUMN_NAME = "subject";',
                WPML_Plugin::getTablename( 'mails' )
            )
        );

        if ( ! empty( $charset ) && strpos( $charset, 'utf8mb4' ) !== false && defined( 'DB_NAME' ) ) {
            // If we're here then migration_1() is already done.
            $message_index = $wpdb->get_results(
                $wpdb->prepare(
                    'SHOW INDEX FROM `%1$s`.`%2$s`
                    WHERE Column_name = "message";',
                    DB_NAME,
                    WPML_Plugin::getTablename( 'mails' )
                )
            );

            if ( ! empty( $message_index ) ) {
                // If we're here then migration_2() is already done.
                update_option( self::OPTION_NAME, 2, false );
            }
        }

        update_option( self::MIGRATION_NOTICE_DISMISS_DB_KEY, true, false );
    }

    /**
     * Run the migrations.
     *
     * @since 1.12.0
     *
     * @param int $version The version of migration to run.
     *
     * @return void
     */
    private function run( $version ) {

        if ( method_exists( $this, "migrate_to_{$version}" ) ) {
            $this->{"migrate_to_{$version}"}();

            return;
        }

        $this->error = "Unable to find migration to version {$version}.";
    }

    /**
     * Display the migration-related notices.
     *
     * @since 1.12.0
     *
     * @return void
     */
    public function display_migration_notice() {

        if (
            ! $this->is_wp_mail_logging_admin_page() ||
            ( ! empty( $_GET['tab'] ) && $_GET['tab'] === 'settings' ) ||
            ! $this->is_migration_needed() ||
            get_option( self::MIGRATION_ADMIN_NOTICE_DISMISS_DB_KEY, false )
        ) {
            return;
        }
        ?>
            <div id="wp-mail-logging-db-upgrade-admin-notice" class="notice notice-info is-dismissible" data-nonce="<?php echo esc_attr( wp_create_nonce( self::MIGRATION_NOTICE_DISMISS_NONCE ) ); ?>">
                <p>
                    <?php
                    printf(
                        wp_kses(
                            __( 'An optional database optimization upgrade is available. Click <a href="%s">here</a> to learn more.', 'wp-mail-logging' ),
                            [
                                'a' => [
                                    'href' => []
                                ],
                            ]
                        ),
                        esc_url( add_query_arg( 'tab', 'settings', WPML_Utils::get_admin_page_url() ) )
                    ); ?>
                </p>
            </div>
        <?php
    }

    /**
     * Display the migration result.
     *
     * @since 1.12.0
     *
     * @return void
     */
    public function display_migration_result() {

        if (
            ! $this->is_wp_mail_logging_admin_page() ||
            ( ! empty( $_GET['tab'] ) && $_GET['tab'] !== 'settings' )
        ) {
            return;
        }

        if ( ! empty( $this->error ) && ! $this->is_success ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( $this->error ); ?></p>
            </div>
            <?php
        }

        if ( $this->is_success ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__( 'Database upgrade completed.', 'wp-mail-logging' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Display the migration section in Settings.
     *
     * @since 1.12.0
     *
     * @param string $tab Current tab in WP Mail Logging page.
     *
     * @return void
     */
    public function display_migration_section( $tab ) {

        if ( ! $this->is_migration_needed() || $tab !== 'settings' || $this->is_success ) {
            return;
        }
        ?>
        <div id="wp-mail-logging-setting-db-upgrade"
             class="wp-mail-logging-setting-row wp-mail-logging-settings-bottom wp-mail-logging-setting-row-content wp-mail-logging-clearfix section-heading"
             data-dismiss="<?php echo esc_attr( wp_create_nonce( self::MIGRATION_NOTICE_DISMISS_NONCE ) ); ?>">
            <div class="wp-mail-logging-setting-field">
                <h3><?php echo esc_html__( 'Database Upgrade', 'wp-mail-logging' ) ?></h3>
            </div>

            <p>
                <?php
                    esc_attr_e( 'This upgrade will include the following:', 'wp-mail-logging' );
                ?>
            </p>

            <ul>
                <li>
                    <?php
                        printf(
                            esc_html__( 'Support for non-UTF8 characters like emojis (%s).', 'wp-mail-logging' ),
                            '🚀🥳❤️'
                        );
                    ?>
                </li>
                <li>
                    <?php esc_html_e( 'Faster email log search by message.', 'wp-mail-logging' ); ?>
                </li>
            </ul>

            <p>
                <i><?php esc_html_e( 'If you do not need these improvements, you can keep using the plugin as is and dismiss this banner in the top right corner.', 'wp-mail-logging' ); ?></i>
            </p>

            <p>
                <?php
                    echo wp_kses(
                        __( '<strong>Important!</strong> By performing this upgrade, <strong>ALL your existing logs will be deleted</strong>.', 'wp-mail-logging' ),
                        [
                            'strong' => [],
                        ]
                    )
                ?>
            </p>

            <p>
                <?php
                echo wp_kses(
                    __( '<i>If you wish to keep all email logs and get the above improvements as well, please <a href="https://wordpress.org/support/topic/how-to-keep-your-email-logs-with-manual-v1-12-0-database-upgrade/" target="_blank">read our manual upgrade guide</a> for more information.</i>', 'wp-mail-logging' ),
                    [
                        'i' => [],
                        'a' => [
                            'href' => [],
                            'target' => [],
                        ],
                    ]
                )
                ?>
            </p>

            <p>

            </p>

            <p>
                <?php esc_html_e( 'Please create a backup of your database before performing the upgrade.', 'wp-mail-logging' ); ?>
            </p>

            <p>
                <button id="wp-mail-logging-btn-db-upgrade" class="button button-primary wp-mail-logging-btn wp-mail-logging-btn-lg" type="button"><?php esc_attr_e( 'Upgrade', 'wp-mail-logging' ); ?></button>
            </p>

            <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-mail-logging' ); ?></span></button>
        </div>
        <?php
    }

    /**
     * Attempt to run older migration.
     *
     * @since 1.12.0
     *
     * @param int $version The version of migration to run.
     *
     * @return void
     */
    private function maybe_run_older_migration( $version ) {

        if ( version_compare( $this->get_current_version(), $version, '<' ) ) {
            $this->run( $version );
        }
    }

    /**
     * Migration from 0 to 1.
     * Convert the columns charset to utf8mb4.
     *
     * @since 1.12.0
     *
     * @return void
     */
    private function migrate_to_1() {

        global $wpdb;

        if ( strpos( $wpdb->collate, 'utf8mb4' ) !== false ) {
            $query = $wpdb->prepare(
                'ALTER TABLE %1$s
                        MODIFY `host` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %2$s NOT NULL DEFAULT "0",
                        MODIFY `receiver` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %3$s NOT NULL DEFAULT "0",
                        MODIFY `subject` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %4$s NOT NULL DEFAULT "0",
                        MODIFY `message` TEXT CHARACTER SET utf8mb4 COLLATE %5$s,
                        MODIFY `headers` TEXT CHARACTER SET utf8mb4 COLLATE %6$s,
                        MODIFY `attachments` VARCHAR(800) CHARACTER SET utf8mb4 COLLATE %7$s NOT NULL DEFAULT "0",
                        MODIFY `error` VARCHAR(400) CHARACTER SET utf8mb4 COLLATE %8$s NULL DEFAULT "",
                        MODIFY `plugin_version` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %9$s NOT NULL DEFAULT "0";',
                WPML_Mail::get_table(),
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate
            );

            if ( $wpdb->query( $query ) === false ) {
                $this->set_error_msg( $wpdb->last_error, 1 );
                return;
            }

            $this->is_success = true;

            // Update the DB version.
            update_option( self::OPTION_NAME, 1, false );
        }
    }

    /**
     * Migration from 1 to 2.
     *
     * This migration alters the table to add a FULL TEXT index on `message` column.
     * For optimization reason, we truncate (delete all the existing logs) the table before adding the index.
     *
     * @since 1.12.0
     *
     * @return void
     */
    private function migrate_to_2() {

        $this->maybe_run_older_migration( 1 );

        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'TRUNCATE TABLE `%1$s`;',
                WPML_Mail::get_table()
            )
        );

        // Add the FULLTEXT INDEX.
        $query = $wpdb->query(
                $wpdb->prepare(
                'ALTER TABLE `%1$s` ADD FULLTEXT INDEX `idx_message` (`message`);',
                WPML_Mail::get_table()
            )
        );

        if ( $query === false ) {
            $this->set_error_msg( $wpdb->last_error, 2 );
            return;
        }

        $this->is_success = true;

        // Update the DB version.
        update_option( self::OPTION_NAME, 2, false );
    }

    /**
     * Set the error message.
     *
     * @since 1.12.0
     *
     * @param string $error   Error occured during migration.
     * @param int    $version Version of migration.
     *
     * @return void
     */
    private function set_error_msg( $error, $version ) {

        $this->error = "Unable to complete migration to version {$version}. Error: {$error}";
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

        $strings['db_upgrade_message'] = esc_html__( 'This upgrade will delete all of your existings logs. Are you sure you want to proceed?', 'wp-mail-logging' );
        $strings['db_upgrade_url']     = esc_url(
            add_query_arg(
                [
                    'tab'       => 'settings',
                    'migration' => '1',
                    'nonce'     => wp_create_nonce( self::MIGRATION_NONCE ),
                ],
                WPML_Utils::get_admin_page_url()
            )
        );

        return $strings;
    }
}
