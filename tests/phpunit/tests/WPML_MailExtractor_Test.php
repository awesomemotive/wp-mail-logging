<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Tests\Helper\WPML_UnitTestCase;
use No3x\WPML\WPML_MailExtractor;

class WPML_MailExtractor_Test extends WPML_UnitTestCase {

    /** @var WPML_MailExtractor */
    private $mailExtractor;

    const EXAMPLE_MAIL_ADDRESS1 = 'example@example.com';
    const EXAMPLE_MAIL_ADDRESS2 = 'example2@example.com';

    function setUp() {
        parent::setUp();
        $this->mailExtractor = new WPML_MailExtractor();
    }

    /**
     * The sanitizer removes evil code from the text to output.
     * It removes unsafe html and keeps html comments.
     * @dataProvider receiverProvider
     * @param $mailArray string the message to be sanitized
     * @param $expected string the expected output
     */
    function test_receiver($mailArray, $expected) {
        $this->assertEquals($expected, $this->mailExtractor->extract($mailArray)->get_receiver());
    }

    /*
     * (string|array) (Required) Array or comma-separated list of email addresses to send message.
     */
    function receiverProvider() {
        $example1And2Expected = self::EXAMPLE_MAIL_ADDRESS1 . ',\n' . self::EXAMPLE_MAIL_ADDRESS2;
        return [
            'single receiver' => [
                WPMailArrayBuilder::aMail()->withTo(self::EXAMPLE_MAIL_ADDRESS1)->build(),
                self::EXAMPLE_MAIL_ADDRESS1
            ],
            'multiple receivers' => [
                WPMailArrayBuilder::aMail()->withTo(self::EXAMPLE_MAIL_ADDRESS1 . ',' . self::EXAMPLE_MAIL_ADDRESS2)->build(),
                $example1And2Expected
            ],
            'array with single receiver' => [
                WPMailArrayBuilder::aMail()->withTo([self::EXAMPLE_MAIL_ADDRESS1])->build(),
                self::EXAMPLE_MAIL_ADDRESS1
            ],
            'array with multiple receivers' => [
                WPMailArrayBuilder::aMail()->withTo([self::EXAMPLE_MAIL_ADDRESS1, self::EXAMPLE_MAIL_ADDRESS2])->build(),
                $example1And2Expected
            ],
        ];
    }

    /**
     * $subject (string) (Required) Email subject
     */
    function test_subject() {
        $subject = "This is a subject";
        $mailArray = WPMailArrayBuilder::aMail()->withSubject($subject)->build();
        $this->assertEquals($subject, $this->mailExtractor->extract($mailArray)->get_subject());
    }

    /**
     * $message (string) (Required) Message contents
     */
    function test_message() {
        $message = "This is a subject";
        $mailArray = WPMailArrayBuilder::aMail()->withMessage($message)->build();
        $this->assertEquals($message, $this->mailExtractor->extract($mailArray)->get_message());
    }

    /**
     * Mandrill stores the message in the 'html' field instead of the 'message' field (see gh-22)
     */
    function test_mandrillMail() {
        $message = "This is a message in the html field";
        $mailArray = WPMailArrayBuilder::aMail()->withMessage($message)->buildAsMandrillMail();
        $this->assertEquals($message, $this->mailExtractor->extract($mailArray)->get_message());
    }

    /**
     * The sanitizer removes evil code from the text to output.
     * It removes unsafe html and keeps html comments.
     * @dataProvider headersProvider
     * @param $mailArray string the message to be sanitized
     * @param $expected string the expected output
     */
    function test_headers($mailArray, $expected) {
        $this->assertEquals($expected, $this->mailExtractor->extract($mailArray)->get_headers());
    }

    function headersProvider() {
        $exampleHeader = 'Content-Type: text/html; charset=UTF-8';
        $example1And2Expected = $exampleHeader . ',\n' . $exampleHeader;
        return [
            'single header' => [
                WPMailArrayBuilder::aMail()->withHeaders($exampleHeader)->build(),
                $exampleHeader
            ],
            'multiple header' => [
                WPMailArrayBuilder::aMail()->withHeaders($exampleHeader . ',\n' . $exampleHeader)->build(),
                $example1And2Expected
            ],
            'array with single header' => [
                WPMailArrayBuilder::aMail()->withHeaders([$exampleHeader])->build(),
                $exampleHeader
            ],
            'array with multiple headers' => [
                WPMailArrayBuilder::aMail()->withHeaders([$exampleHeader, $exampleHeader])->build(),
                $example1And2Expected
            ],
        ];
    }

    /**
     * $attachments (string|array) (Optional) Files to attach.
     * Default value: array()
     */
    function test_attachments() {
        $attachments = ["file.pdf"];
        $mailArray = WPMailArrayBuilder::aMail()->withAttachments($attachments)->build();
        $expectedAttachment = "bla/file.pdf";
        //TODO: mock FS
        $this->assertEquals($expectedAttachment, $this->mailExtractor->extract($mailArray)->get_attachments());
    }

}

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

}
