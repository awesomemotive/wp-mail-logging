<?php

namespace No3x\WPML\Tests\Unit;

use No3x\WPML\Tests\Helper\WPMailArrayBuilder;
use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_MailExtractor;

/**
 * Class WPML_MailExtractor_Test tests the mail extraction from a WordPress mail array specified at the codex
 * @see https://developer.wordpress.org/reference/functions/wp_mail/
 * @package No3x\WPML\Tests
 */
class WPML_MailExtractor_Test extends \PHPUnit_Framework_TestCase {

    /** @var WPML_MailExtractor */
    private $mailExtractor;

    function setUp() {
        parent::setUp();
        $this->mailExtractor = new WPML_MailExtractor();
    }

    /**
     * Codex: (string|array) (Required) Array or comma-separated list of email addresses to send message.
     * @dataProvider receiverProvider
     * @param $mailArray array the mailArray
     * @param $expected string the expected output
     */
    function test_receiver($mailArray, $expected) {
        $this->assertEquals($expected, $this->mailExtractor->extract($mailArray)->get_receiver());
    }

    function receiverProvider() {
        $exampleEmail1 = 'example@example.com';
        $exampleEmail2 = 'example2@example.com';
        $example1And2Expected = $exampleEmail1 . ',\n' . $exampleEmail2;
        return [
            'single receiver' => [
                WPMailArrayBuilder::aMail()->withTo($exampleEmail1)->build(),
                $exampleEmail1
            ],
            'multiple receivers' => [
                WPMailArrayBuilder::aMail()->withTo($exampleEmail1 . ',' . $exampleEmail2)->build(),
                $example1And2Expected
            ],
            'array with single receiver' => [
                WPMailArrayBuilder::aMail()->withTo([$exampleEmail1])->build(),
                $exampleEmail1
            ],
            'array with multiple receivers' => [
                WPMailArrayBuilder::aMail()->withTo([$exampleEmail1, $exampleEmail2])->build(),
                $example1And2Expected
            ],
        ];
    }

    /**
     * Codex: $subject (string) (Required) Email subject
     */
    function test_subject() {
        $subject = "This is a subject";
        $mailArray = WPMailArrayBuilder::aMail()->withSubject($subject)->build();
        $this->assertEquals($subject, $this->mailExtractor->extract($mailArray)->get_subject());
    }

    /**
     * Codex: $message (string) (Required) Message contents
     */
    function test_message() {
        $message = "This is a message";
        $mailArray = WPMailArrayBuilder::aMail()->withMessage($message)->build();
        $this->assertEquals($message, $this->mailExtractor->extract($mailArray)->get_message());
    }

    /**
     * @requires PHPUnit 5.7
     * expectException is not present in older phpunit version.
     */
    function test_no_messageField_throws_exception() {
        $mailArray = WPMailArrayBuilder::aMail()->buildWithoutMessage();
        $this->expectException("Exception");
        $this->expectExceptionMessage(WPML_MailExtractor::ERROR_NO_FIELD);
        $this->mailExtractor->extract($mailArray);
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
     * Codex: $headers (string|array) (Optional) Additional headers.
     * Default value: ''
     * @dataProvider headersProvider
     * @param $mailArray array the mailArray
     * @param $expected string the expected output
     */
    function test_headers($mailArray, $expected) {
        $this->assertEquals($expected, $this->mailExtractor->extract($mailArray)->get_headers());
    }

    function headersProvider() {
        $exampleHeader = 'Content-Type: text/html; charset=UTF-8';
        $example1And2Expected = $exampleHeader . ',\n' . $exampleHeader;
        return [
            'none header' => [
                WPMailArrayBuilder::aMail()->buildWithoutHeaders(),
                ''
            ],
            'empty header' => [
                WPMailArrayBuilder::aMail()->but()->withNoHeaders()->build(),
                ''
            ],
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
     * Codex: $attachments (string|array) (Optional) Files to attach.
     * Default value: array()
     * @dataProvider attachmentsProvider
     * @param $mailArray array the mailArray
     * @param $expected string the expected output
     */
    function test_attachments($mailArray, $expected) {
        $this->assertEquals($expected, $this->mailExtractor->extract($mailArray)->get_attachments());
    }

    function attachmentsProvider() {
        $exampleAttachment1 = WP_CONTENT_DIR . '/uploads/2018/05/file.pdf';
        $exampleAttachment2 = WP_CONTENT_DIR . '/uploads/2018/01/bill.pdf';

        $exampleAttachment1Expected = '/2018/05/file.pdf';
        $example1And2Expected = '/2018/05/file.pdf,\n/2018/01/bill.pdf';

        return [
            'none attachments' => [
                WPMailArrayBuilder::aMail()->buildWithoutAttachments(),
                ''
            ],
            'empty attachments' => [
                WPMailArrayBuilder::aMail()->but()->withNoAttachments()->build(),
                ''
            ],
            'no wp-content in path' => [
                WPMailArrayBuilder::aMail()->withAttachments('/tmp/0file.png')->build(),
                '0file.png'
            ],
            'single attachment' => [
                WPMailArrayBuilder::aMail()->withAttachments($exampleAttachment1)->build(),
                $exampleAttachment1Expected
            ],
            'multiple attachments' => [
                WPMailArrayBuilder::aMail()->withAttachments($exampleAttachment1 . ',\n' . $exampleAttachment2)->build(),
                $example1And2Expected
            ],
            'array with single attachment' => [
                WPMailArrayBuilder::aMail()->withAttachments([$exampleAttachment1])->build(),
                $exampleAttachment1Expected
            ],
            'array with multiple attachments' => [
                WPMailArrayBuilder::aMail()->withAttachments([$exampleAttachment1, $exampleAttachment2])->build(),
                $example1And2Expected
            ],
        ];
    }
}
