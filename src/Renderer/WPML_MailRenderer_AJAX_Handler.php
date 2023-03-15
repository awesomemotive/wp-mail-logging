<?php

namespace No3x\WPML\Renderer;

use No3x\WPML\Admin\SettingsTab;
use No3x\WPML\IHooks;
use No3x\WPML\WPML_Utils;

use \Exception;

class WPML_MailRenderer_AJAX_Handler implements IHooks {

    const ERROR_NONCE_MESSAGE = 'Issue with nonce.';
    const ERROR_NONCE_CODE = -1;
    const ERROR_ID_MISSING_MESSAGE = 'No ID passed to render.';
    const ERROR_ID_MISSING_CODE = -2;
    const ERROR_UNKNOWN_FORMAT_MESSAGE = 'Unknown format.';
    const ERROR_UNKNOWN_FORMAT_CODE  = -3;
    const ERROR_OTHER_CODE  = -4;

    /**
     * Action hook used by the AJAX class.
     *
     * @var string
     */
    const ACTION = 'wpml_email_render';

    /**
     * Action argument used by the nonce validating the AJAX request.
     *
     * @var string
     */
    const NONCE = 'wpml-modal-load-mail';

    /** @var WPML_MailRenderer_AJAX_Handler */
    private static $handler;
    /**
     * @var WPML_MailRenderer
     */
    private $mailRenderer;

    /**
     * @param WPML_MailRenderer $mailRenderer
     */
    public function setMailRenderer($mailRenderer) {
        self::getInstance()->mailRenderer = $mailRenderer;
    }

    public function setPluginMeta($plugin_meta) {
        self::getInstance()->plugin_meta = $plugin_meta;
    }

    public function __construct(WPML_MailRenderer $mailRenderer) {
        if( null == self::$handler ) {
            self::$handler = $this;
        }
        self::getInstance()->setMailRenderer($mailRenderer);
    }

    public static function getInstance() {
        return self::$handler;
    }

    /**
     * Register the AJAX handler class with all the appropriate WordPress hooks.
     */
    function addActionsAndFilters() {
        add_action('wp_ajax_' . self::ACTION, array(self::$handler, 'handle'));
    }

    /**
     * Get the AJAX data that WordPress needs to output.
     *
     * @return array
     */
    public function get_ajax_data() {
        return [
            'action'         => self::ACTION,
            'default_format' => 'html',
            'nonce'          => wp_create_nonce(self::NONCE)
        ];
    }

    /**
     * Handles the AJAX request for my plugin.
     */
    public function handle() {

        $this->checkNonce();

        $settings = SettingsTab::get_settings( SettingsTab::DEFAULT_SETTINGS );

        if ( ! current_user_can( $settings['can-see-submission-data'] ) ) {
            wp_send_json_error( [
                'code'    => self::ERROR_OTHER_CODE,
                'message' => 'Invalid request!'
            ] );
        }

        $id     = $this->checkAndGetId();
        $format = $this->checkAndGetFormat();

        try {
            $rendered = $this->mailRenderer->render($id, $format);
            wp_send_json_success($rendered);
        } catch (Exception $e) {
            // On the WP test framework die is not called with wp_send_json but a WPAjaxDieContinueException is thrown
            // that conflicts with the generic Exception catcher that returns the error as json.
            if($this->isAjaxTestCondition($e)) {
                throw $e;
            }
            if( $e->getMessage() === self::ERROR_UNKNOWN_FORMAT_MESSAGE) {
                wp_send_json_error(['code' => self::ERROR_UNKNOWN_FORMAT_CODE, 'message' =>  self::ERROR_UNKNOWN_FORMAT_MESSAGE]);
            }
            wp_send_json_error(['code' => self::ERROR_OTHER_CODE, 'message' =>  $e->getMessage()]);
        }
    }

    private function isAjaxTestCondition($e) {
        return get_class($e) === "WPAjaxDieContinueException";
    }

    private function checkNonce() {
        $validNonce = check_ajax_referer(self::NONCE, false, false);

        if (!$validNonce) {
            wp_send_json_error(['code' => self::ERROR_NONCE_CODE, 'message' => self::ERROR_NONCE_MESSAGE]);
        }
    }

    private function checkAndGetId() {
        if (!isset($_POST['id'])) {
            wp_send_json_error(['code' => self::ERROR_ID_MISSING_CODE, 'message' => self::ERROR_ID_MISSING_MESSAGE]);
        }
        return intval( $_POST['id'] );
    }

    private function checkAndGetFormat() {
        $format_requested = isset($_POST['format']) ? $_POST['format'] : WPML_MailRenderer::FORMAT_HTML;
        if(!in_array($format_requested, $this->mailRenderer->getSupportedFormats())) {
            wp_send_json_error(['code' => self::ERROR_UNKNOWN_FORMAT_CODE, 'message' =>  self::ERROR_UNKNOWN_FORMAT_MESSAGE]);
        }
        return $format_requested;
    }

}
