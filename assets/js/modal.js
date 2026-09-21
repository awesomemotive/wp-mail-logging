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
            $('#wp-mail-logging-modal-wrap').fadeIn();
        },
        hide: function () {
            $('#wp-mail-logging-modal-wrap').fadeOut();
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

                if ( action === 'delete' ) {

                    $.confirm( {
                        animateFromElement: false,
                        animationBounce: 1,
                        backgroundDismiss: false,
                        buttons: {
                            confirm: {
                                text: WPMailLoggingJqueryConfirm.yes,
                                btnClass: 'btn-confirm',
                                keys: [ 'enter' ],
                                action: function() {
                                    window.location.href = redirectUrl;
                                }
                            },
                            cancel: {
                                text: WPMailLoggingJqueryConfirm.cancel,
                                btnClass: 'btn-cancel',
                            }
                        },
                        content: WPMailLoggingJqueryConfirm.delete_log_confirm_msg,
                        draggable: false,
                        escapeKey: true,
                        theme: 'modern',
                        type: 'orange',
                        typeAnimated: false,
                        title: WPMailLoggingJqueryConfirm.headsup,
                        useBootstrap: false,
                        boxWidth: '400px',
                        icon: '"></i><img src="' + WPMailLoggingJqueryConfirm.icon + '" style="width: 40px; height: 40px;" alt="' + WPMailLoggingJqueryConfirm.warning + '"><i class="'
                    } );
                    return;
                }

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

    /**
     * Close the modal when the user clicks the dim area outside the content box.
     *
     * The transparent content wrap covers the viewport above the backdrop, so we test
     * against the content box rather than the backdrop element. The press must also
     * start outside the box, so a text selection that ends outside does not close it.
     *
     * @since {VERSION}
     */
    var isOutsideModalContent = function ( target ) {
        return $( target ).closest( '#wp-mail-logging-modal-content' ).length === 0;
    };

    var pressedOutsideModalContent = false;

    $( '#wp-mail-logging-modal-wrap' ).on( 'mousedown', function ( e ) {
        pressedOutsideModalContent = isOutsideModalContent( e.target );
    });

    $( '#wp-mail-logging-modal-wrap' ).on( 'click', function ( e ) {
        if ( pressedOutsideModalContent && isOutsideModalContent( e.target ) ) {
            wpml.modal.hide();
        }
    });

    $( document ).on( 'click', '.wp-mail-logging-load-remote', function ( e ) {
        e.preventDefault();

        var $notice = $( this ).closest( '.wp-mail-logging-remote-content-notice' ),
            $iframe = $notice.siblings( 'iframe' ).first();

        if ( $iframe.length <= 0 ) {
            return;
        }

        $iframe.attr( 'src', utils.updateQueryString( 'load_remote', '1', $iframe.attr( 'src' ) ) );
        $notice.remove();
    });

    $(document).keyup(function(e) {
        if (e.keyCode === 27) wpml.modal.hide();
    });
});
