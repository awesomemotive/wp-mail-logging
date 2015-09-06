jQuery(function ($) {

    var wpml = {};

    wpml.modal = {
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
        getSelectedFormat: function() {
            return 'html';
        }
    };

    $('.wp-mail-logging-view-message').click(function () {
        var emailMessage = "";
        var id = $(this).data('mail-id');
        jQuery.post(ajaxurl, {
            'action': 'wpml_email_get',
            //'nonce': top.tinymce.settings.bss_nonce,
            'id': id,
            'format': wpml.modal.getSelectedFormat()
        }, function(response) {
            emailMessage = response;
            wpml.modal.set(emailMessage);
        });

        wpml.modal.show();
    });

    $('.wp-mail-logging-modal-close').click(function () {
        wpml.modal.hide();
    });
});