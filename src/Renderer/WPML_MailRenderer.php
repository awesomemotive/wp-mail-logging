<?php
namespace No3x\WPML\Renderer;

use No3x\WPML\Model\IMailService;
use No3x\WPML\Model\WPML_Mail as Mail;
use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\Renderer\Format\MailRendererFactory;

use \Exception;

class WPML_MailRenderer {

    const FORMAT_RAW = 'raw';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';

    /** @var array */
    private $supported_formats;
    /** @var IMailService */
    private $mailService;

    /**
     * WPML_MailRenderer constructor.
     * @param IMailService $mailService
     */
    public function __construct(IMailService $mailService) {
        $this->mailService = $mailService;
        $this->supported_formats = [self::FORMAT_RAW, self::FORMAT_HTML, self::FORMAT_JSON];
    }

    /**
     * Ajax function to retrieve rendered mail in certain format.
     * @since 1.6.0
     * @param $id
     * @param $format
     * @return string
     * @throws Exception
     */
    public function render($id, $format) {
        /** @var Mail $mail */
        $mail = $this->mailService->find_one( $id );
        if(!$mail) {
            throw new Exception("Requested mail not found in database.");
        }

        return $this->renderMail($mail, $format);
    }

    /**
     * @param $format
     * @param WPML_Mail $mail
     * @return string
     * @throws Exception
     */
    protected function renderMail($mail, $format) {
        $mailAppend = '';
        switch ($format) {
            case self::FORMAT_HTML:
            case self::FORMAT_RAW:
                $mailAppend .= $this->render_mail($mail->to_array(), $format);
                break;
            case self::FORMAT_JSON:
                if ($this->isHtmlMail($mail)) {
                    // Fallback to raw in case it is a html mail
                    $mailAppend .= sprintf("<span class='info'>%s</span>", __("Fallback to raw format because html is not convertible to json.", 'wp-mail-logging'));
                    $mailAppend .= $this->render_mail($mail->to_array(), self::FORMAT_RAW);
                } else {
                    $mailAppend .= "<pre>" . json_encode($this->render_mail($mail->to_array(), self::FORMAT_JSON), JSON_PRETTY_PRINT) . "</pre>";
                }
                break;
            default:
                throw new Exception("Unknown format.");
        }

        return $mailAppend;
    }

    /**
     * @param WPML_Mail $mail
     * @return string
     */
    private function isHtmlMail($mail) {
        return stristr(str_replace(' ', '', $mail->get_headers()), "Content-Type:text/html");
    }

    /**
     * Renders all components of the mail.
     * @since 1.3
     * @param array $item The current item.
     * @param $format
     * @return string The mail as html
     * @throws Exception
     */
    function render_mail( $item, $format ) {
        $renderer = MailRendererFactory::factory($format);
        return $renderer->render($item);
    }

    public function getSupportedFormats() {
        return $this->supported_formats;
    }

}
