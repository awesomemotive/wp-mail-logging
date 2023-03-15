/* global wp_mail_logging_admin_logs */

'use strict';

var WPMailLogging = window.WPMailLogging || {};
WPMailLogging.Admin = WPMailLogging.Admin || {};

WPMailLogging.Admin.Logs = WPMailLogging.Admin.Logs || ( function( document, window, $ ) {

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

            // If there are screen options we have to move them.
			$( '#screen-meta-links, #screen-meta' ).prependTo( '#wp-mail-logging-page-header-temp' ).show();

            app.bindActions();
        },

        /**
         * Bind all actions/events.
         *
         * @since 1.11.0
         */
        bindActions: function() {

            $( document ).on( 'click', '.wp-mail-logging-product-education-dismiss', app.productEducationDismiss );
        },

        /**
         * Event triggered when product education is dismissed.
         *
         * @since 1.11.0
         *
         * @param {Event} e Event object.
         */
        productEducationDismiss: function( e ) {

            e.preventDefault();

            // Find the parent container.
            var $parent = $( this ).parents( '.wp-mail-logging-product-education' ).first();

            if ( $parent.length <= 0 ) {
                return;
            }

            var dataProductEducationID = $parent.data( 'productEducationId' );
            var dataNonce = $parent.data( 'nonce' );

            if ( ! dataProductEducationID || ! dataNonce ) {
                return;
            }

            // Submit AJAX.
            $.post(
                wp_mail_logging_admin_logs.ajaxurl,
                {
                    action: 'wp_mail_logging_product_education_dismiss',
                    nonce: dataNonce,
                    productEducationID: dataProductEducationID
                },
                function( response ) {

                    if ( ! response.success ) {
                        alert( response.data );
                        return;
                    }

                    $parent.fadeTo( 100, 0, function() {
                        $parent.slideUp( 100, function() {
                            $parent.remove();
                        });
                    });
                }
            );
        }
    };

    // Expose to public.
    return app;

} ( document, window, jQuery ) );

WPMailLogging.Admin.Logs.init();
