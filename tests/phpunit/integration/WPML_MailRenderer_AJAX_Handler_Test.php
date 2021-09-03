<?php

namespace No3x\WPML\Tests\Integration;


use Mockery;
use No3x\WPML\Renderer\WPML_MailRenderer_AJAX_Handler;
use No3x\WPML\Renderer\WPML_MailRenderer;

use \Exception;
use WPAjaxDieContinueException;

class WPML_MailRenderer_AJAX_Handler_Test extends \WP_Ajax_UnitTestCase {

    /** @var WPML_MailRenderer */
    private $mailRendererMock;
    /** @var WPML_MailRenderer_AJAX_Handler */
    private $mailRendererAjaxHandler;
    private $valid_id = 2;

    public function setUp() {
        parent::setUp();

        $this->mailRendererMock = Mockery::mock('No3x\WPML\Renderer\WPML_MailRenderer');

        $this->mailRendererMock->shouldReceive('getSupportedFormats')
            ->andReturn(['raw', 'html']);

        $this->mailRendererAjaxHandler = new WPML_MailRenderer_AJAX_Handler($this->mailRendererMock, []);
    }

    /**
     * Test that the callback saves the value for administrators.
     */
    public function test_nonce_invalid() {
        $_POST['_wpnonce'] = $this->mailRendererAjaxHandler->get_ajax_data()["nonce"] . "invalid";

        try {
            $this->_handleAjax( WPML_MailRenderer_AJAX_Handler::ACTION );
            $this->fail( 'Expected exception: WPAjaxDieContinueException' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }//end try

        $this->checkJsonMessage();
        $response = json_decode($this->_last_response, true);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_NONCE_CODE, $response['data']['code']);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_NONCE_MESSAGE, $response['data']['message']);
    }

    /**
     * Test that the callback saves the value for administrators.
     */
    public function test_id_not_passed() {
        $_POST['_wpnonce'] = $this->mailRendererAjaxHandler->get_ajax_data()["nonce"];

        try {
            $this->_handleAjax( WPML_MailRenderer_AJAX_Handler::ACTION );
            $this->fail( 'Expected exception: WPAjaxDieContinueException' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }//end try

        $this->checkJsonMessage();
        $response = json_decode($this->_last_response, true);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_ID_MISSING_CODE, $response['data']['code']);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_ID_MISSING_MESSAGE, $response['data']['message']);
    }

    /**
     * Test that the callback saves the value for administrators.
     */
    public function test_invalid_id_passed() {
        $invalid_id = -1;
        $this->mailRendererMock->shouldReceive('render')
            ->withArgs([$invalid_id, 'html'])
            ->times(1)
            ->andThrow("\Exception", "Requested mail not found in database.");

        $_POST['_wpnonce'] = $this->mailRendererAjaxHandler->get_ajax_data()["nonce"];
        $_POST['format'] = 'html';
        $_POST['id'] = $invalid_id;

        try {
            $this->_handleAjax( WPML_MailRenderer_AJAX_Handler::ACTION );
            $this->fail( 'Expected exception: WPAjaxDieContinueException' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }//end try

        $this->checkJsonMessage();
        $response = json_decode($this->_last_response, true);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_OTHER_CODE, $response['data']['code']);
        $this->assertEquals("Requested mail not found in database.", $response['data']['message']);
    }

    /**
     * Test that the callback saves the value for administrators.
     */
    public function test_unknown_format_passed() {
        $this->mailRendererMock->shouldReceive('render')
            ->withAnyArgs()
            ->times(1)
            ->andReturn("rendered");

        $_POST['_wpnonce'] = $this->mailRendererAjaxHandler->get_ajax_data()["nonce"];
        $_POST['id'] = $this->valid_id;
        $_POST['format'] = 'no-valid-format';

        try {
            $this->_handleAjax( WPML_MailRenderer_AJAX_Handler::ACTION );
            $this->fail( 'Expected exception: WPAjaxDieContinueException' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }//end try

        $this->checkJsonMessage();
        $response = json_decode($this->_last_response, true);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_UNKNOWN_FORMAT_CODE, $response['data']['code']);
        $this->assertEquals(WPML_MailRenderer_AJAX_Handler::ERROR_UNKNOWN_FORMAT_MESSAGE, $response['data']['message']);
    }

    /**
     * Test that the callback saves the value for administrators.
     */
    public function test_render_is_called() {
        $this->mailRendererMock->shouldReceive('render')
            ->withArgs([$this->valid_id, 'html'])
            ->times(1)
            ->andReturn("rendered");

        $_POST['_wpnonce'] = $this->mailRendererAjaxHandler->get_ajax_data()["nonce"];
        $_POST['id'] = $this->valid_id;
        $_POST['format'] = 'html';

        try {
            $this->_handleAjax( WPML_MailRenderer_AJAX_Handler::ACTION );
            $this->fail( 'Expected exception: WPAjaxDieContinueException' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }//end try

        $this->checkJsonMessage(true);
        $response = json_decode($this->_last_response, true);
        $this->mailRendererMock->mockery_verify();
        $this->assertEquals("rendered", $response['data']);
    }

    private function checkJsonMessage($success_expected = false) {
        $this->assertJson( $this->_last_response );
        $response = json_decode( $this->_last_response, true );
        if( $success_expected ) {
            $this->assertTrue( $response['success'] );
            $this->assertArrayHasKey('data', $response);
        } else {
            $this->assertFalse( $response['success'] );
            $this->assertArrayHasKey('code', $response["data"]);
            $this->assertArrayHasKey('message', $response["data"]);
        }
    }

}
