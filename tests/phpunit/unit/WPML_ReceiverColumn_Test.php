<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\Format\HTMLRenderer;
use No3x\WPML\Renderer\Column\ReceiverColumn;
use No3x\WPML\Renderer\WPML_ColumnManager;

/**
 * Class WPML_ReceiverColumn_Test tests the display normalization of the receiver column.
 *
 * The extractor joins multiple receivers with the literal two-character sequence `\n`
 * (see WPML_MailExtractor::joinArrayWithCommaAndNewLine). That literal is a de-facto
 * internal delimiter relied upon by WPML_Email_Resender, so it stays in the database.
 * These tests pin the display-time normalization instead.
 *
 * @package No3x\WPML\Tests
 */
class WPML_ReceiverColumn_Test extends \PHPUnit_Framework_TestCase {

    /** @var ReceiverColumn */
    private $column;

    function setUp() {
        parent::setUp();
        $this->column = new ReceiverColumn();
    }

    private function render( $receiver ) {
        return $this->column->render( [ 'receiver' => $receiver ], ColumnFormat::FULL );
    }

    function test_single_receiver_is_unchanged() {
        $this->assertEquals( 'user1@example.com', $this->render( 'user1@example.com' ) );
    }

    function test_literal_newline_delimiter_is_replaced_with_comma_space() {
        $this->assertEquals(
            'user1@example.com, user2@example.com',
            $this->render( 'user1@example.com,\n user2@example.com' )
        );
    }

    function test_literal_carriage_return_delimiter_is_replaced_with_comma_space() {
        $this->assertEquals(
            'user1@example.com, user2@example.com',
            $this->render( 'user1@example.com,\r\n user2@example.com' )
        );
    }

    function test_real_newline_delimiter_is_replaced_with_comma_space() {
        $this->assertEquals(
            'user1@example.com, user2@example.com',
            $this->render( "user1@example.com,\n user2@example.com" )
        );
    }

    function test_display_names_are_preserved() {
        $this->assertEquals(
            'Bob <bob@example.com>, Ann <ann@example.com>',
            $this->render( 'Bob <bob@example.com>,\n Ann <ann@example.com>' )
        );
    }

    function test_three_receivers_are_all_normalized() {
        $this->assertEquals(
            'a@example.com, b@example.com, c@example.com',
            $this->render( 'a@example.com,\n b@example.com,\n c@example.com' )
        );
    }

    function test_empty_receiver_renders_empty_string() {
        $this->assertEquals( '', $this->render( '' ) );
    }

    function test_trailing_delimiter_does_not_produce_empty_entry() {
        $this->assertEquals( 'user1@example.com', $this->render( 'user1@example.com,\n' ) );
    }

    function test_column_manager_returns_the_receiver_column_renderer() {
        $columnManager = new WPML_ColumnManager();

        $this->assertInstanceOf(
            'No3x\WPML\Renderer\Column\ReceiverColumn',
            $columnManager->getColumnRenderer( WPML_ColumnManager::COLUMN_RECEIVER )
        );
    }

    /**
     * The modal renders the receiver through BaseRenderer::render_column_value() rather
     * than through the column manager, so it needs its own normalization.
     */
    private function renderModalValue( $key, $value ) {
        $renderer = new HTMLRenderer( new WPML_ColumnManager() );

        $method = new \ReflectionMethod( 'No3x\WPML\Renderer\Format\BaseRenderer', 'render_column_value' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $renderer, $key, $value );

        return trim( strip_tags( ob_get_clean() ) );
    }

    function test_modal_normalizes_the_receiver() {
        $this->assertEquals(
            'user1@example.com, user2@example.com',
            $this->renderModalValue( 'receiver', 'user1@example.com,\n user2@example.com' )
        );
    }

    function test_modal_still_escapes_the_receiver() {
        $this->assertEquals(
            'a@example.com, &lt;script&gt;alert(1)&lt;/script&gt;',
            $this->renderModalValue( 'receiver', 'a@example.com,\n <script>alert(1)</script>' )
        );
    }

    /**
     * The SanitizedColumnDecorator applies esc_html() only when the wrapped column
     * reports itself as the receiver column, so this name must not drift.
     */
    function test_column_name_stays_receiver_so_escaping_still_applies() {
        $this->assertEquals( WPML_ColumnManager::COLUMN_RECEIVER, $this->column->getColumnName() );
    }
}
