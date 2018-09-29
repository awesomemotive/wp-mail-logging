<?php
namespace No3x\WPML;

use No3x\WPML\Model\IMailService;
use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Printer\ColumnFormat;
use No3x\WPML\Printer\EscapingColumnDecorator;
use No3x\WPML\Printer\SanitizedColumnDecorator;
use No3x\WPML\Printer\WPML_ColumnManager;

class WPML_FormattedPrinter implements IHooks {

    /** @var array */
    private $supported_formats;
    /** @var IMailService */
    private $mailService;
    /** @var WPML_ColumnManager */
    private $columnManager;

    /**
     * WPML_FormattedPrinter constructor.
     * @param IMailService $mailService
     * @param array $supported_formats
     */
    public function __construct(IMailService $mailService, $supported_formats = array()) {
        $this->mailService = $mailService;
        $this->supported_formats = $supported_formats;
        $this->columnManager = new WPML_ColumnManager();
    }

    function addActionsAndFilters() {
        add_action( 'wp_ajax_wpml_email_get', [$this, 'ajax_wpml_email_get'] );
    }

    public function ajax_wpml_email_get() {
        check_ajax_referer( 'wpml-modal-show', 'ajax_nonce', true );

        if( ! isset( $_POST['id'] ) )
            wp_die( "huh?" );
        $id = intval( $_POST['id'] );

        $format_requested = isset( $_POST['format'] ) ? $_POST['format'] : 'html';
        if ( ! in_array( $format_requested, $this->supported_formats ) )  {
            echo "Unsupported Format. Using html as fallback.";
            $format_requested = WPML_Utils::sanitize_expected_value($format_requested, $this->supported_formats, 'html');
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

        $mailAppend = '';
        switch( $format ) {
            case 'html':
            case 'raw': {
                $mailAppend .= $this->render_mail( $mail->to_array(), $format );
                break;
            }
            case 'json': {
                if( stristr( str_replace(' ', '', $mail->get_headers()),  "Content-Type:text/html")) {
                    // Fallback to raw in case it is a html mail
                    $mailAppend .= sprintf("<span class='info'>%s</span>", __("Fallback to raw format because html is not convertible to json.", 'wp-mail-logging' ) );
                    $mailAppend .= $this->render_mail( $mail->to_array(), 'raw' );
                } else {
                    $mailAppend .= "<pre>" . json_encode( $this->render_mail( $mail->to_array(), 'json' ), JSON_PRETTY_PRINT)  . "</pre>";
                }
                break;
            }
            default:
                throw new \Exception("Unknown format.");
        }

        return $mailAppend;
    }

    /**
     * Renders all components of the mail.
     * @since 1.3
     * @param array $item The current item.
     * @param $format
     * @return string The mail as html
     */
    function render_mail( $item, $format ) {
        $mailAppend = '';
        foreach ($item as $column_name => $value) {
            $content = '';

            $title = "<span class=\"title\">{$this->getTranslation($column_name)}: </span>";

            if ('raw' === $format || 'json' === $format) {
                $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
                if ($column_name !== WPML_ColumnManager::COLUMN_ERROR && WPML_ColumnManager::COLUMN_ATTACHMENTS) {
                    $column_format = ColumnFormat::FULL;
                } else {
                    $column_format = ColumnFormat::SIMPLE;
                }
            } elseif ('html' === $format) {
                $column_renderer = (new SanitizedColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
                if ($column_name === WPML_ColumnManager::COLUMN_HEADERS) {
                    $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
                } else {
                    $column_format = ColumnFormat::FULL;
                }
            }
            if( isset($column_renderer) && isset($column_format) ) {
                $content = $column_renderer->render($item, $column_format);
            }

            if (!in_array($column_name, $this->getIgnoredColumns())) {
                $mailAppend .= $title . $content;
            }
            if ('json' === $format) {
                $json[$column_name] = $content;
            }
        }
        if('json' === $format) {
            return $json;
        }
        return $mailAppend;
    }

    private function getTranslation($column_name) {
        return $this->columnManager->getTranslationForColumn($column_name);
    }

    /**
     * @return mixed
     */
    private function getIgnoredColumns() {
        return [
            WPML_ColumnManager::COLUMN_MAIL_ID,
            WPML_ColumnManager::COLUMN_PLUGIN_VERSION
        ];
    }

}
