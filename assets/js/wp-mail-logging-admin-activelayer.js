/* global wp_mail_logging_admin_activelayer */

'use strict';

var WPMailLogging = window.WPMailLogging || {};
WPMailLogging.Admin = WPMailLogging.Admin || {};

WPMailLogging.Admin.ActiveLayer = WPMailLogging.Admin.ActiveLayer || ( function( document, window, $ ) {

    /**
     * Elements.
     *
     * @since {VERSION}
     *
     * @type {object}
     */
    var el = {};

    /**
     * Public functions and properties.
     *
     * @since {VERSION}
     *
     * @type {object}
     */
    var app = {

        /**
         * Start the engine. DOM is not ready yet, use only to init something.
         *
         * @since {VERSION}
         */
        init: function() {

            $( app.ready );
        },

        /**
         * DOM is fully loaded.
         *
         * @since {VERSION}
         */
        ready: function() {

            app.initVars();
            app.bindActions();
        },

        /**
         * Init variables.
         *
         * @since {VERSION}
         */
        initVars: function() {

            el = {
                $stepInstall:    $( 'section.step-install' ),
                $stepInstallNum: $( 'section.step-install .num img' ),
                $stepSetup:      $( 'section.step-setup' ),
                $stepSetupNum:   $( 'section.step-setup .num img' ),
            };
        },

        /**
         * Bind all actions/events.
         *
         * @since {VERSION}
         */
        bindActions: function() {

            // Step 'Install' button click.
            el.$stepInstall.on( 'click', 'button', app.stepInstallClick );

            // Step 'Setup' button click.
            el.$stepSetup.on( 'click', 'button', app.gotoURL );
        },

        /**
         * Step 'Install' button click.
         *
         * @since {VERSION}
         */
        stepInstallClick: function() {

            var $btn = $( this ),
                action = $btn.attr( 'data-action' ),
                plugin = $btn.attr( 'data-plugin' ),
                ajaxAction = '';

            if ( $btn.hasClass( 'disabled' ) ) {
                return;
            }

            switch ( action ) {
                case 'activate':
                    ajaxAction = 'wp_mail_logging_activate_activelayer';
                    $btn.text( wp_mail_logging_admin_activelayer.activating );
                    break;

                case 'install':
                    ajaxAction = 'wp_mail_logging_install_activelayer';
                    $btn.text( wp_mail_logging_admin_activelayer.installing );
                    break;

                case 'goto-url':
                    window.location.href = $btn.attr( 'data-url' );
                    return;

                default:
                    return;
            }

            $btn.addClass( 'disabled' );
            app.showSpinner( el.$stepInstallNum );

            var data = {
                action: ajaxAction,
                nonce : wp_mail_logging_admin_activelayer.nonce,
                plugin: plugin
            };

            $.post( wp_mail_logging_admin_activelayer.ajaxurl, data )
                .done( function( res ) {
                    app.stepInstallDone( res, $btn, action );
                } )
                .always( function() {
                    app.hideSpinner( el.$stepInstallNum );
                }
            );
        },

        /**
         * Display spinner.
         *
         * @since {VERSION}
         *
         * @param {jQuery} $el Section number image jQuery object.
         */
        showSpinner: function( $el ) {

            $el.siblings( '.loader' ).removeClass( 'hidden' );
        },

        /**
         * Done part of the 'Install' step.
         *
         * @since {VERSION}
         *
         * @param {object} res    Result of $.post() query.
         * @param {jQuery} $btn   Button.
         * @param {string} action Action (for more info look at the app.stepInstallClick() function).
         */
        stepInstallDone: function( res, $btn, action ) {

            var success = 'install' === action ? res.success && res.data.is_activated : res.success;

            if ( success ) {
                el.$stepInstallNum.attr( 'src', el.$stepInstallNum.attr( 'src' ).replace( 'step-1.', 'step-complete.' ) );
                $btn.addClass( 'grey' ).removeClass( 'button-primary' ).text( wp_mail_logging_admin_activelayer.activated );
                app.enableStepSetup();

                return;
            }

            var activationFail = ( 'install' === action && res.success && ! res.data.is_activated ) || 'activate' === action,
                url            = ! activationFail ? wp_mail_logging_admin_activelayer.manual_install_url : wp_mail_logging_admin_activelayer.manual_activate_url,
                msg            = ! activationFail ? wp_mail_logging_admin_activelayer.error_could_not_install : wp_mail_logging_admin_activelayer.error_could_not_activate,
                btn            = ! activationFail ? wp_mail_logging_admin_activelayer.download_now : wp_mail_logging_admin_activelayer.plugins_page;

            $btn.removeClass( 'grey disabled' ).text( btn ).attr( 'data-action', 'goto-url' ).attr( 'data-url', url );
            $btn.after( '<p class="error">' + msg + '</p>' );
        },

        /**
         * Enable the step 'Setup' section after a successful install/activate.
         *
         * The setup button already carries its target URL (the ActiveLayer
         * settings page) from the server, so we only need to enable it.
         *
         * @since {VERSION}
         */
        enableStepSetup: function() {

            el.$stepSetup.removeClass( 'grey' );
            el.$stepSetup
                .find( 'button' )
                .removeClass( 'grey disabled' )
                .addClass( 'button-primary' )
                .text( wp_mail_logging_admin_activelayer.settings );
        },

        /**
         * Hide spinner.
         *
         * @since {VERSION}
         *
         * @param {jQuery} $el Section number image jQuery object.
         */
        hideSpinner: function( $el ) {

            $el.siblings( '.loader' ).addClass( 'hidden' );
        },

        /**
         * Go to URL by click on the button.
         *
         * @since {VERSION}
         */
        gotoURL: function() {

            var $btn = $( this );

            if ( $btn.hasClass( 'disabled' ) ) {
                return;
            }

            window.location.href = $btn.attr( 'data-url' );
        }
    };

    // Expose to the public.
    return app;

} ( document, window, jQuery ) );

WPMailLogging.Admin.ActiveLayer.init();
