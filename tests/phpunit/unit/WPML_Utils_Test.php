<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_Utils;

class WPML_Utils_Test extends \PHPUnit_Framework_TestCase {

    public function test_determine_fa_icon() {
        // test file icon rendered properly (gh-90)
        $this->assertNotSame('<i class="fa fa-file-file-o"></i>', WPML_Utils::determine_fa_icon("file"));
        $this->assertSame('<i class="fa fa-file-o"></i>', WPML_Utils::determine_fa_icon("file"));
        $this->assertSame('<i class="fa fa-file-archive-o"></i>', WPML_Utils::determine_fa_icon("archive"));
    }

    /**
     * The sanitizer removes evil code from the text to output.
     * It removes unsafe html and keeps html comments.
     * @dataProvider expectedValuesProvider
     * @param $in string the message to be sanitized
     * @param $expected string the expected output
     */
    function test_sanitizeExpectedValue($in, $expected) {
        $this->assertSame($expected, WPML_Utils::sanitize_expected_value($in['value'], $in['allowed_values'], $in['default_value']));
    }

    function expectedValuesProvider() {
        return [
            'allowed value is allowed value' => [
                [
                    'value' => 'a',
                    'allowed_values' => 'a',
                    'default_value' => null
                ],
                'a'
            ],
            'allowed value in set' => [
                [
                    'value' => 'a',
                    'allowed_values' => [
                        'a'
                    ],
                    'default_value' => null
                ],
                'a'
            ],
            'allowed value in set with others' => [
                [
                    'value' => 'a',
                    'allowed_values' => [
                        'b', 'a', 'c'
                    ],
                    'default_value' => null
                ],
                'a'
            ],
            'default if no match' => [
                [
                    'value' => 'a',
                    'allowed_values' => [
                        'b'
                    ],
                    'default_value' => 'hello world'
                ],
                'hello world'
            ],
            'false if no match and no meaningful default' => [
                [
                    'value' => 'a',
                    'allowed_values' => [
                        'b'
                    ],
                    'default_value' => null
                ],
                false
            ],
        ];
    }
}
