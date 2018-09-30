jQuery(function ($) {

    var wpml = {};

    wpml.modal = {
        self : this,
        id : undefined,
        selectedFormat: undefined,
        init: function () {
            var selected = $('#wp-mail-logging-modal-content-header-format-switch').find('.checked');
            var selectedFormat = $(selected).children("input").attr('id');
            wpml.modal.setSelectedFormat(selectedFormat);
            $('#wp-mail-logging-modal-content-header-format-switch input').on('ifChecked', function( event ) {
                wpml.modal.setSelectedFormat( $(this).attr('id') );
            });
        },
        clear: function () {
            $('#wp-mail-logging-modal-content-body-content').html('');
        },
        set: function ($value) {
            $('#wp-mail-logging-modal-content-body-content').html($value);
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

    $('.wp-mail-logging-view-message').click(function () {
        wpml.modal.id = $(this).data('mail-id');
        wpml.modal.init();
        wpml.modal.show();
    });
    $('.wp-mail-logging-modal-close').click(function () {
        wpml.modal.hide();
    });
    $(document).keyup(function(e) {
        if (e.keyCode === 27) wpml.modal.hide();
    });
});
