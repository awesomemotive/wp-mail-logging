<?php
namespace No3x\WPML;

use No3x\WPML\Model\IMailService;
use No3x\WPML\Model\WPML_Mail as Mail;

class WPML_FormattedPrinter implements IHooks {

    /**
     * @var array
     */
    private $supported_formats;
    /**
     * @var IMailService
     */
    private $mailService;

    /**
     * WPML_FormattedPrinter constructor.
     * @param IMailService $mailService
     * @param array $supported_formats
     */
    public function __construct(IMailService $mailService, $supported_formats = array()) {
        $this->mailService = $mailService;
        $this->supported_formats = $supported_formats;
    }

    function addActionsAndFilters() {
        add_filter( WPML_Plugin::HOOK_LOGGING_SUPPORTED_FORMATS, function() {
            return $this->supported_formats;
        } );
        add_action( 'wp_ajax_wpml_email_get', [$this, 'ajax_wpml_email_get'] );
    }

    public function ajax_wpml_email_get() {
        $formats = is_array( $additional = apply_filters( WPML_Plugin::HOOK_LOGGING_SUPPORTED_FORMATS, array() ) ) ? $additional : array();

        check_ajax_referer( 'wpml-modal-show', 'ajax_nonce', true );

        if( ! isset( $_POST['id'] ) )
            wp_die( "huh?" );
        $id = intval( $_POST['id'] );

        $format_requested = isset( $_POST['format'] ) ? $_POST['format'] : 'html';
        if ( ! in_array( $format_requested, $formats ) )  {
            echo "Unsupported Format. Using html as fallback.";
            $format_requested = WPML_Utils::sanitize_expected_value($format_requested, $formats, 'html');
        }

        echo $this->print_email($id, $format_requested);
        wp_die(); // this is required to terminate immediately and return a proper response
    }

    /**
     * Ajax function to retrieve rendered mail in certain format.
     * @since 1.6.0
     */
    public function print_email($id, $format) {
        /** @var Mail $mail */
        $mail = $this->mailService->find_one( $id );
        /* @var $instance WPML_Email_Log_List */
        $instance = WPML_Init::getInstance()->getService( 'emailLogList' );
        $mailAppend = '';
        switch( $format ) {
            case 'html': {
                $mailAppend .= $instance->render_mail_html( $mail->to_array() );
                break;
            }
            case 'raw': {
                $mailAppend .= $instance->render_mail( $mail->to_array() );
                break;
            }
            case 'json': {
                if( stristr( str_replace(' ', '', $mail->get_headers()),  "Content-Type:text/html")) {
                    // Fallback to raw in case it is a html mail
                    $mailAppend .= sprintf("<span class='info'>%s</span>", __("Fallback to raw format because html is not convertible to json.", 'wp-mail-logging' ) );
                    $mailAppend .= $instance->render_mail( $mail->to_array() );
                } else {
                    $mailAppend .= "<pre>" . htmlentities(json_encode( $mail->to_array(), JSON_PRETTY_PRINT))  . "</pre>";
                }
                break;
            }
            default:
                $mailAppend .= apply_filters( WPML_Plugin::HOOK_LOGGING_FORMAT_CONTENT . "_{$format}", $mail->to_array() );
                break;
        }

        return $instance->sanitize_text($mailAppend);
    }
}
