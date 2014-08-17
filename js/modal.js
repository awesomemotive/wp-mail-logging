jQuery(function($){

	var wpml = {};

	wpml.modal = {

		clear: function(){

			$( '#wp-mail-logging-modal-content-body-content' ).html('');

		},

		set: function( $value ){

			$( '#wp-mail-logging-modal-content-body-content' ).html( $value );

		},

		show: function(){

			$( '#wp-mail-logging-modal-wrap' ).fadeIn();

		},

		hide: function () {

			$( '#wp-mail-logging-modal-wrap' ).fadeOut();

		}

	}

	$( '.wp-mail-logging-view-message' ).click(function(){

		var emailMessage = $( this ).data('message');
		wpml.modal.set( emailMessage );
		wpml.modal.show();

	});

	$( '.wp-mail-logging-modal-close' ).click(function(){

		wpml.modal.hide();

	});

});