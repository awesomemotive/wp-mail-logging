jQuery(function ($) {

    var wpml = {};

    wpml.modal = {
        self : this,
        id : undefined,
        selectedFormat: 'html',
        init: function () {
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
        setSelectedFormat: function( $newFormat ) {
            this.selectedFormat = $newFormat;
            jQuery.post(ajaxurl, {
                'action': 'wpml_email_get',
                //'nonce': top.tinymce.settings.bss_nonce,
                'id': wpml.modal.id,
                'format': wpml.modal.selectedFormat
            }, function(response) {
                emailMessage = response;
                wpml.modal.set(emailMessage);
            });

        },
        getSelectedFormat: function() {
            return this.selectedFormat;
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
});