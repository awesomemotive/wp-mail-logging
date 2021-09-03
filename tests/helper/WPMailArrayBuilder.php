<?php

namespace No3x\WPML\Tests\Helper;

/**
 * Class WPMailArrayBuilder builds message arrays as they are applied to the wp_mail filter.
 * @package No3x\WPML\Tests
 */
class WPMailArrayBuilder {
    private $to;
    private $subject;
    private $message;
    private $headers;
    private $attachments;

    private function __construct() {
        $this->to = 'example@exmple.com';
        $this->subject = 'The subject';
        $this->message = 'This is a message';
        $this->headers = '';
        $this->attachments = [];
    }

    public static function aMail() {
        return new WPMailArrayBuilder();
    }

    public function withTo($to) {
        $this->to = $to;
        return $this;
    }

    public function withSubject($subject) {
        $this->subject = $subject;
        return $this;
    }

    public function withMessage($message) {
        $this->message = $message;
        return $this;
    }

    public function withNoHeaders() {
        $this->headers = '';
        return $this;
    }

    public function withHeaders($headers) {
        $this->headers = $headers;
        return $this;
    }

    public function withNoAttachments() {
        $this->attachments = '';
        return $this;
    }

    public function withAttachments($attachments) {
        $this->attachments = $attachments;
        return $this;
    }

    public function but() {
        return WPMailArrayBuilder::aMail()
            ->withTo($this->to)
            ->withSubject($this->subject)
            ->withMessage($this->message)
            ->withHeaders($this->headers)
            ->withAttachments($this->attachments);
    }

    public function build() {
        return [
            'to' => $this->to,
            'subject' => $this->subject,
            'message' => $this->message,
            'headers' => $this->headers,
            'attachments' => $this->attachments
        ];
    }

    public function buildAsMandrillMail() {
        $mandrillMail = $this->build();
        $mandrillMail['html'] = $mandrillMail['message'];
        unset($mandrillMail['message']);
        return $mandrillMail;
    }

    public function buildWithoutHeaders() {
        $mailArray = $this->build();
        unset($mailArray['headers']);
        return $mailArray;
    }

    public function buildWithoutAttachments() {
        $mailArray = $this->build();
        unset($mailArray['attachments']);
        return $mailArray;
    }

    public function buildWithoutMessage() {
        $mailArray = $this->build();
        unset($mailArray['message']);
        return $mailArray;
    }

}
