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
            jQuery.post(ajaxurl, {
                'action': 'wpml_email_get',
                'ajax_nonce': wpml_modal.ajax_nonce,
                'id': wpml.modal.id,
                'format': wpml.modal.selectedFormat
            }, function(response) {
                emailMessage = response;
                wpml.modal.set(emailMessage);
            });
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