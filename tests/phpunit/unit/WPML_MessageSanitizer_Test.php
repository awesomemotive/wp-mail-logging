<?php

namespace No3x\WPML\Tests\Unit;

use No3x\WPML\WPML_MessageSanitizer;

class WPML_MessageSanitizer_Test extends \PHPUnit_Framework_TestCase {

    /** @var WPML_MessageSanitizer */
    private $messageSanitizer;

    function setUp() {
        parent::setUp();
        $this->messageSanitizer = new WPML_MessageSanitizer();
    }

    /**
     * The sanitizer removes evil code from the text to output.
     * It removes unsafe html and keeps html comments.
     * @dataProvider messagesProvider
     * @param $message string the message to be sanitized
     * @param $expected string the expected output
     */
    function test_messageSanitation($message, $expected) {
        $this->assertEquals($expected, $this->messageSanitizer->sanitize($message));
    }

    function messagesProvider() {
        return [
            "plaintext" => [
                "Hello World",
                "Hello World"
            ],
            "html bold" => [
                "<b>Hello World</b>",
                "<b>Hello World</b>"
            ],
            "style" => [
                "<style>body {background-color: red;}</style>",
                "<style>body {background-color: red;}</style>"
            ],
            "script alert()" => [
                "<script>alert('XSS hacking!');</script>",
                "alert('XSS hacking!');"
            ],
            "html comment" => [
                "<!-- Comment -->",
                "<!-- Comment -->"
            ],
            "html encoded comment" => [
                "&lt;!-- Comment --&gt;",
                "&lt;!-- Comment --&gt;"
            ],
            "html embedded tag in comment" => [
                "<!-- <b>This is commented out actually</b> -->",
                "<!-- <b>This is commented out actually</b> -->"
            ],
        ];
    }
}
