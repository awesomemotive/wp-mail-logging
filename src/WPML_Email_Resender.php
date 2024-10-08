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
     *
     * @param WPML_Mail $mail
     */
    public function resendMail($mail) {
        $receivers = explode( "\\n", str_replace( "\\r\\n", "\\n", $mail->get_receiver() ) );
        $receivers = array_map(function ($receiver) {
            return rtrim($receiver, ",");
        }, $receivers);

        $attachments = explode( "\\n", str_replace( "\\r\\n", "\\n", $mail->get_attachments() ) );
        $attachments = array_map(function ($attachments) {
            return rtrim($attachments, ",");
        }, $attachments);
        $attachments = array_map(function ($attachments) {
            return WPML_Attachment::fromRelPath($attachments)->getPath();
        }, $attachments);

        $this->dispatcher->dispatch( $receivers, $mail->get_subject(), $mail->get_message(), WPML_Utils::clean_headers( $mail->get_headers() ), $attachments );
    }

}
