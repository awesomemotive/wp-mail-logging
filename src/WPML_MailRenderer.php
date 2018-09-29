<?php
namespace No3x\WPML;

use No3x\WPML\Model\IMailService;
use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Printer\ColumnFormat;
use No3x\WPML\Printer\EscapingColumnDecorator;
use No3x\WPML\Printer\SanitizedColumnDecorator;
use No3x\WPML\Printer\WPML_ColumnManager;

class WPML_MailRenderer implements IHooks {

    const FORMAT_RAW = 'raw';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';

    /** @var array */
    private $supported_formats;
    /** @var IMailService */
    private $mailService;
    /** @var WPML_ColumnManager */
    private $columnManager;

    /**
     * WPML_MailRenderer constructor.
     * @param IMailService $mailService
     */
    public function __construct(IMailService $mailService) {
        $this->mailService = $mailService;
        $this->supported_formats = [self::FORMAT_RAW, self::FORMAT_HTML, self::FORMAT_JSON];
        $this->columnManager = new WPML_ColumnManager();
    }

    function addActionsAndFilters() {
        add_action( 'wp_ajax_wpml_email_render', [$this, 'ajax_wpml_email_render'] );
    }

    public function ajax_wpml_email_render() {
        $validNonce = check_ajax_referer( 'wpml-modal-show', 'ajax_nonce', false );

        if( !$validNonce) {
            wp_send_json_error(['code' => -1, 'message' =>  'Issue with nonce.']);
        }

        if( !isset( $_POST['id'] ) ) {
            wp_send_json_error(['code' => -2, 'message' =>  'No ID passed to render.']);
        }
        $id = intval( $_POST['id'] );

        $format_requested = isset( $_POST['format'] ) ? $_POST['format'] : 'html';
        if ( ! in_array( $format_requested, $this->supported_formats ) )  {
            echo "Unsupported Format. Using html as fallback.";
            $format_requested = WPML_Utils::sanitize_expected_value($format_requested, $this->supported_formats, 'html');
        }

        try {
            $rendered = $this->render($id, $format_requested);
            wp_send_json_success($rendered);
        } catch (\Exception $e) {
            if( $e->getMessage() == "Unknown format.") {
                wp_send_json_error(['code' => -3, 'message' =>  $e->getMessage()]);
            }
            wp_send_json_error(['code' => -4, 'message' =>  $e->getMessage()]);
        }

    }

    /**
     * Ajax function to retrieve rendered mail in certain format.
     * @since 1.6.0
     * @throws \Exception
     */
    public function render($id, $format) {
        /** @var Mail $mail */
        $mail = $this->mailService->find_one( $id );

        $mailAppend = '';
        switch( $format ) {
            case self::FORMAT_HTML:
            case self::FORMAT_RAW: {
                $mailAppend .= $this->render_mail( $mail->to_array(), $format );
                break;
            }
            case self::FORMAT_JSON: {
                if( stristr( str_replace(' ', '', $mail->get_headers()),  "Content-Type:text/html")) {
                    // Fallback to raw in case it is a html mail
                    $mailAppend .= sprintf("<span class='info'>%s</span>", __("Fallback to raw format because html is not convertible to json.", 'wp-mail-logging' ) );
                    $mailAppend .= $this->render_mail( $mail->to_array(), self::FORMAT_RAW );
                } else {
                    $mailAppend .= "<pre>" . json_encode( $this->render_mail( $mail->to_array(), self::FORMAT_JSON ), JSON_PRETTY_PRINT)  . "</pre>";
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
     * @throws \Exception
     */
    function render_mail( $item, $format ) {

        if(!in_array($format, $this->supported_formats)) {
            throw new \Exception("Unknown format.");
        }

        $mailAppend = '';
        foreach ($item as $column_name => $value) {
            $content = '';
            $title = "<span class=\"title\">{$this->getTranslation($column_name)}: </span>";

            if (self::FORMAT_RAW === $format || self::FORMAT_JSON === $format) {
                $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
                if ($column_name !== WPML_ColumnManager::COLUMN_ERROR && $column_name !== WPML_ColumnManager::COLUMN_ATTACHMENTS) {
                    $column_format = ColumnFormat::FULL;
                } else {
                    $column_format = ColumnFormat::SIMPLE;
                }
            } elseif (self::FORMAT_HTML === $format) {
                $column_renderer = (new SanitizedColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
                if ($column_name === WPML_ColumnManager::COLUMN_HEADERS) {
                    $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
                } else {
                    $column_format = ColumnFormat::FULL;
                }
            }

            /** @var IColumn $column_renderer */
            if( isset($column_renderer) && isset($column_format) ) {
                $content = $column_renderer->render($item, $column_format);
            }

            if (!in_array($column_name, $this->getIgnoredColumns())) {
                $mailAppend .= $title . $content;
            }
            if (self::FORMAT_JSON === $format) {
                $json[$column_name] = $content;
            }
        }
        if(self::FORMAT_JSON === $format) {
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

    public function getSupportedFormats() {
        return $this->supported_formats;
    }

}
