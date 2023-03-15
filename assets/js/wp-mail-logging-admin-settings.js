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
        }
    };

    // Expose to public.
    return app;
}( document, window, jQuery ) );

WPMailLogging.Admin.Settings.init();
