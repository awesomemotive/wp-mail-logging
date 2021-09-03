<?php

namespace No3x\WPML\Renderer\Format;


use No3x\WPML\Renderer\WPML_ColumnManager;
use No3x\WPML\Renderer\WPML_MailRenderer;

class MailRendererFactory {

    /**
     * @param $format
     * @return IMailRenderer
     * @throws \Exception
     */
    static function factory($format) {

        $columnManager = new WPML_ColumnManager();

        switch ($format) {
            case WPML_MailRenderer::FORMAT_RAW:
                return new RawRenderer($columnManager);
            case WPML_MailRenderer::FORMAT_HTML:
                return new HTMLRenderer($columnManager);
            case WPML_MailRenderer::FORMAT_JSON:
                return new JSONRenderer($columnManager);
            default:
                throw new \Exception("Unknown format.");
        }
    }
}
