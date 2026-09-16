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

    /**
     * Regression guard for #227: sanitizing a null message must not raise a
     * PHP 8.1+ "Passing null to parameter" deprecation.
     *
     * Without the (string) cast in sanitize(), the null message reaches
     * str_replace() and wp_kses() as null, which PHP 8.1+ reports as an
     * E_DEPRECATED notice. The result is "" either way, so asserting only the
     * return value would pass with or without the fix; this test captures
     * deprecations so removing the cast fails it.
     */
    function test_nullMessageIsSanitizedWithoutDeprecation() {
        $deprecations = [];
        set_error_handler(
            function ($errno, $errstr) use (&$deprecations) {
                $deprecations[] = $errstr;
                return true;
            },
            E_DEPRECATED
        );

        try {
            $result = $this->messageSanitizer->sanitize(null);
        } catch (\Exception $e) {
            restore_error_handler();
            throw $e;
        }
        restore_error_handler();

        $this->assertSame('', $result);
        $this->assertSame(
            [],
            $deprecations,
            'sanitize(null) must not trigger a PHP deprecation; got: ' . implode(' | ', $deprecations)
        );
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
