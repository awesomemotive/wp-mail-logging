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
                ['to' => self::EXAMPLE_MAIL_ADDRESS1],
                self::EXAMPLE_MAIL_ADDRESS1
            ],
            'multiple receivers' => [
                ['to' => self::EXAMPLE_MAIL_ADDRESS1 . ',' . self::EXAMPLE_MAIL_ADDRESS2],
                $example1And2Expected
            ],
            'array with single receiver' => [
                ['to' => [self::EXAMPLE_MAIL_ADDRESS1]],
                self::EXAMPLE_MAIL_ADDRESS1
            ],
            'array with multiple receivers' => [
                ['to' => [self::EXAMPLE_MAIL_ADDRESS1, self::EXAMPLE_MAIL_ADDRESS2]],
                $example1And2Expected
            ],
        ];
    }

}
