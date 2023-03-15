/* global wp_mail_logging_admin_logs */

jQuery(function ($) {

    var wpml = {};

    wpml.modal = {
        self : this,
        id : undefined,
        selectedFormat: undefined,
        init: function () {
            // Initially load the preferred format.
            var $selected = $( '.wp-mail-logging-active-format' );
            var initialFormat = wpml_modal_ajax.default_format;

            if ( $selected.length > 0 && $selected.data( 'format' ) ) {
                initialFormat = $selected.data( 'format' );
            }

            wpml.modal.setSelectedFormat( initialFormat );

            wpml.modal.bindActions();
        },
        bindActions: function() {
            // Change format action.
            $( document ).on( 'click', '.wp-mail-logging-modal-format', function( e ) {
                e.preventDefault();

                var format = $( this ).data( 'format' );

                if ( ! format ) {
                    return;
                }

                wpml.modal.setSelectedFormat( format );
            });

            $( document ).on( 'click', '.wp-mail-logging-html-error-notice > .notice-dismiss', function( e ) {
                e.preventDefault();

                $noticeElem = $( this ).parent( '.wp-mail-logging-html-error-notice' );

                if ( $noticeElem.length <= 0 ) {
                    return;
                }

                $noticeElem.fadeTo( 100, 0, function() {
                    $noticeElem.slideUp( 100, function() {
                        $noticeElem.remove();
                    });
                });
            } );
        },
        clear: function () {
            $('#wp-mail-logging-modal-content-body-content').html('');
        },
        set: function( $value ) {
            $( '.wp-mail-logging-modal-format' ).removeClass( 'wp-mail-logging-active-format' );
            $( `#wp-mail-logging-modal-format-${ wpml.modal.selectedFormat }` ).addClass( 'wp-mail-logging-active-format' );

            $('#wp-mail-logging-modal-content-body-content').html( $value );
        },
        show: function () {
            // Work around to fix the education banner not fading immediately due to position being relative.
            $( '#wp-mail-logging-product-education-email-logs-bottom' ).css( 'z-index', -1 );

            $('#wp-mail-logging-modal-wrap').fadeIn();
        },
        hide: function () {
            $('#wp-mail-logging-modal-wrap').fadeOut( 400, function() {
                $( '#wp-mail-logging-product-education-email-logs-bottom' ).css( 'z-index', '' );
            } );
        },
        setSelectedFormat: function( newFormat ) {
            wpml.modal.selectedFormat = newFormat;
                jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    'action': wpml_modal_ajax.action,
                    '_ajax_nonce': wpml_modal_ajax.nonce,
                    'id': wpml.modal.id,
                    'format': wpml.modal.selectedFormat
                },
                success: wpml.modal.ajaxResponse,
                error: wpml.modal.ajaxError
            });
        },
        ajaxResponse: function( response_data, textStatus, XMLHttpRequest ) {
            if (response_data.success) {
                wpml.modal.set(response_data.data);
            } else {
                wpml.modal.set("Error (" + response_data.data.code + "): '" + response_data.data.message + "'");
            }
        },
        ajaxError: function (XMLHttpRequest, textStatus, errorThrown) {
            wpml.modal.set(errorThrown);
        }
    };

    var utils = {
        /**
         * Update query string in URL.
         *
         * @since 1.11.0
         */
        updateQueryString: function( key, value, url ) {

            var re = new RegExp( '([?&])' + key + '=.*?(&|#|$)(.*)', 'gi' ),
                hash;

            if ( re.test( url ) ) {
                if ( typeof value !== 'undefined' && value !== null )
                    return url.replace( re, '$1' + key + '=' + value + '$2$3' );
                else {
                    hash = url.split( '#' );
                    url = hash[0].replace( re, '$1$3' ).replace( /(&|\?)$/, '' );
                    if ( typeof hash[1] !== 'undefined' && hash[1] !== null )
                        url += '#' + hash[1];
                    return url;
                }
            } else {
                if ( typeof value !== 'undefined' && value !== null ) {
                    var separator = url.indexOf( '?' ) !== -1 ? '&' : '?';
                    hash = url.split( '#' );
                    url = hash[0] + separator + key + '=' + value;
                    if ( typeof hash[1] !== 'undefined' && hash[1] !== null )
                        url += '#' + hash[1];
                    return url;
                }
                else
                    return url;
            }
        }
    };

    $( '.wp-mail-logging-action-item' ).click( function ( e ) {
        e.preventDefault();

        if ( typeof wp_mail_logging_admin_logs === 'undefined' ||
            typeof wp_mail_logging_admin_logs.single_log_action_nonce === 'undefined' ||
            typeof wp_mail_logging_admin_logs.single_log_action_key === 'undefined' ||
            typeof wp_mail_logging_admin_logs.admin_email_logs_url === 'undefined'
        ) {
            return;
        }

        var $this   = $( this ),
            $parent = $this.parent( '.wp-mail-logging-action-column' );

        if ( $parent.length <= 0 ) {
            return;
        }

        var mailId = $parent.data( 'mail-id' ),
            action = $this.data( 'action' );

        if ( ! mailId || ! action ) {
            return;
        }

        if ( action === 'resend' || action === 'delete' ) {

            var redirectUrl = utils.updateQueryString( 'action', action, wp_mail_logging_admin_logs.admin_email_logs_url );
                redirectUrl = utils.updateQueryString( 'email_log_id', mailId, redirectUrl );
                redirectUrl = utils.updateQueryString( wp_mail_logging_admin_logs.single_log_action_key, wp_mail_logging_admin_logs.single_log_action_nonce, redirectUrl );

            window.location.href = redirectUrl;
            return;
        }

        wpml.modal.id = mailId;
        wpml.modal.init();
        wpml.modal.show();
    });

    $( '.wp-mail-logging-modal-close' ).click( function ( e ) {
        e.preventDefault();
        wpml.modal.hide();
    });
    $(document).keyup(function(e) {
        if (e.keyCode === 27) wpml.modal.hide();
    });
});
