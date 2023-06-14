'use strict';

var WPMailLogging = window.WPMailLogging || {};
WPMailLogging.Admin = WPMailLogging.Admin || {};

WPMailLogging.Admin.Settings = WPMailLogging.Admin.Settings || ( function( document, window, $ ) {

    var app = {
        /**
         * Start the engine. DOM is not ready yet, use only to init something.
         *
         * @since 1.11.0
         */
        init: function() {

            $( app.ready );
        },

        /**
         * DOM is fully loaded.
         *
         * @since 1.11.0
         */
        ready: function() {

            app.bindActions();
        },

        /**
         * Bind all actions/events.
         *
         * @since 1.11.0
         * @since {VERSION} Add support for dismissing the database upgrade notice.
         */
        bindActions: function() {

            $( document ).on( 'click', '.wp-mail-logging-settings-toggle', function () {
                const $this = $( this );
                const togglesId = $this.data( 'toggles-id' );
                const $togglesIdDOM = $( `#wp-mail-logging-setting-tab-row-${ togglesId }` );

                if ( ! togglesId || $togglesIdDOM.length <= 0 ) {
                    return;
                }

                if ( $this.is( ':checked') ) {
                    $togglesIdDOM.show();
                } else {
                    $togglesIdDOM.hide();
                }
            } );

            $( document ).on( 'click', '#wp-mail-logging-setting-db-upgrade .notice-dismiss', function ( e ) {

                e.preventDefault();

                var $notice = $( this ).closest( '#wp-mail-logging-setting-db-upgrade' );

                if ( $notice.length <= 0 ) {
                    return;
                }

                $notice.fadeTo( 100, 0, function() {
                    $notice.slideUp( 100, function() {
                        $notice.remove();
                    } );
                } );

                // Get nonce.
                var $nonce = $notice.data( 'dismiss' );

                if ( ! $nonce ) {
                    return;
                }

                $.post(
                    ajaxurl,
                    {
                        'action': 'wp_mail_logging_dismiss_db_upgrade_notice',
                        'nonce': $nonce
                    }
                )
            } );
        }
    };

    // Expose to public.
    return app;
}( document, window, jQuery ) );

WPMailLogging.Admin.Settings.init();
