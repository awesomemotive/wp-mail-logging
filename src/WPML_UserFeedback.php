<?php

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail as Mail;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asking users for their experience with this plugin.
 *
 * @since 1.10.5
 */
class WPML_UserFeedback implements IHooks {

    /**
     * The wp option for notice dismissal data.
     */
    const OPTION_NAME = 'wp_mail_logging_user_feedback_notice';

    /**
     * How many days after activation it should display the user feedback notice.
     */
    const DELAY_NOTICE = 14;

    /**
     * Add actions and filters for this module.
     */
    public function addActionsAndFilters() {

        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_notices', [ $this, 'maybe_display' ] );
        add_action( 'wp_ajax_wp_mail_logging_feedback_notice_dismiss', [ $this, 'feedback_notice_dismiss' ] );
    }

    /**
     * Maybe display the user feedback notice.
     */
    public function maybe_display() {

        // Only admin users should see the feedback notice.
        if ( ! is_super_admin() ) {
            return;
        }

        $options = get_option( self::OPTION_NAME );

        // Set default options.
        if ( empty( $options ) ) {
            $options = [
                'time'      => time(),
                'dismissed' => false,
            ];
            update_option( self::OPTION_NAME, $options );
        }

        // Check if the feedback notice was not dismissed already.
        if ( isset( $options['dismissed'] ) && ! $options['dismissed'] ) {
            $this->display();
        }
    }

    /**
     * Display the user feedback notice.
     */
    private function display() {

        // Fetch when plugin was initially activated.
        $activated = get_option( 'wp_mail_logging_activated_time' );

        // Skip if the plugin is active for less than a defined number of days.
        if ( empty( $activated ) || ( $activated + ( DAY_IN_SECONDS * self::DELAY_NOTICE ) ) > time() ) {
            return;
        }

        // Only display the notice if our plugin is being used (has at least 10 email logs).
        $total_logs = Mail::query()->search( false )->find( true );

        if ( $total_logs < 10 ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible wp-mail-logging-review-notice">
            <div class="wp-mail-logging-review-step wp-mail-logging-review-step-1">
                <p><?php esc_html_e( 'Are you enjoying WP Mail Logging?', 'wp-mail-logging' ); ?></p>
                <p>
                    <a href="#" class="wp-mail-logging-review-switch-step"
                       data-step="3"><?php esc_html_e( 'Yes', 'wp-mail-logging' ); ?></a><br/>
                    <a href="#" class="wp-mail-logging-review-switch-step"
                       data-step="2"><?php esc_html_e( 'Not Really', 'wp-mail-logging' ); ?></a>
                </p>
            </div>
            <div class="wp-mail-logging-review-step wp-mail-logging-review-step-2" style="display: none">
                <p><?php esc_html_e( 'We\'re sorry to hear you aren\'t enjoying WP Mail Logging. We would love a chance to improve. Could you take a minute and let us know what we can do better?', 'wp-mail-logging' ); ?></p>
                <p>
                    <?php
                    printf(
                        '<a href="https://sendlayer.com/wp-mail-logging-plugin-feedback/" class="wp-mail-logging-dismiss-review-notice wp-mail-logging-review-out" target="_blank" rel="noopener noreferrer">%s</a>',
                        esc_html__( 'Give Feedback', 'wp-mail-logging' )
                    );
                    ?>
                    <br>
                    <a href="#" class="wp-mail-logging-dismiss-review-notice" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'No thanks', 'wp-mail-logging' ); ?>
                    </a>
                </p>
            </div>
            <div class="wp-mail-logging-review-step wp-mail-logging-review-step-3" style="display: none">
                <p><?php esc_html_e( 'That’s awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress to help us spread the word and boost our motivation?', 'wp-mail-logging' ); ?></p>
                <p><strong><?php esc_html_e( '~ WP Mail Logging team', 'wp-mail-logging' ) ?></strong></p>
                <p>
                    <a href="https://wordpress.org/support/plugin/wp-mail-logging/reviews/?filter=5#new-post"
                       class="wp-mail-logging-dismiss-review-notice wp-mail-logging-review-out" target="_blank"
                       rel="noopener noreferrer">
                        <?php esc_html_e( 'OK, you deserve it', 'wp-mail-logging' ); ?>
                    </a><br>
                    <a href="#" class="wp-mail-logging-dismiss-review-notice" target="_blank"
                       rel="noopener noreferrer"><?php esc_html_e( 'Nope, maybe later', 'wp-mail-logging' ); ?></a><br>
                    <a href="#" class="wp-mail-logging-dismiss-review-notice" target="_blank"
                       rel="noopener noreferrer"><?php esc_html_e( 'I already did', 'wp-mail-logging' ); ?></a>
                </p>
            </div>
        </div>
        <script type="text/javascript">
            jQuery( document ).ready( function( $ ) {
                $( document ).on( 'click', '.wp-mail-logging-dismiss-review-notice, .wp-mail-logging-review-notice button', function( e ) {
                    if (! $( this ).hasClass( 'wp-mail-logging-review-out' )) {
                        e.preventDefault();
                    }
                    $.post( ajaxurl, {action: 'wp_mail_logging_feedback_notice_dismiss'} );
                    $( '.wp-mail-logging-review-notice' ).remove();
                } );

                $( document ).on( 'click', '.wp-mail-logging-review-switch-step', function( e ) {
                    e.preventDefault();
                    var target = parseInt( $( this ).attr( 'data-step' ), 10 );

                    if (target) {
                        var $notice = $( this ).closest( '.wp-mail-logging-review-notice' );
                        var $review_step = $notice.find( '.wp-mail-logging-review-step-' + target );

                        if ($review_step.length > 0) {
                            $notice.find( '.wp-mail-logging-review-step:visible' ).fadeOut( function() {
                                $review_step.fadeIn();
                            } );
                        }
                    }
                } );
            } );
        </script>
        <?php
    }

    /**
     * Dismiss the user feedback admin notice.
     */
    public function feedback_notice_dismiss() {

        $options              = get_option( self::OPTION_NAME, [] );
        $options['time']      = time();
        $options['dismissed'] = true;

        update_option( self::OPTION_NAME, $options );

        if ( is_super_admin() && is_multisite() ) {
            $site_list = get_sites();
            foreach ( (array) $site_list as $site ) {
                switch_to_blog( $site->blog_id );

                update_option( self::OPTION_NAME, $options );

                restore_current_blog();
            }
        }

        wp_send_json_success();
    }
}
