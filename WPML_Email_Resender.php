<?php

namespace No3x\WPML;

use No3x\WPML\Model\WPML_Mail;

class WPML_Email_Resender {

    /** @var  WPML_Email_Dispatcher $dispatcher */
    private $dispatcher;

    public function __construct($dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Resend mail
     * @param WPML_Mail $mail
     */
    public function resendMail($mail) {
        $headers = explode( "\\n", str_replace( "\\r\\n", "\\n", $mail->get_headers() ) );
        $headers = array_map(function ($header) {
            return rtrim($header, ",");
        }, $headers);

        $this->dispatcher->dispatch($mail->get_receiver(), $mail->get_subject(), $mail->get_message(), $headers, $mail->get_attachments() );
    }

}
