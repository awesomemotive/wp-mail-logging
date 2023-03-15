<?php

namespace No3x\WPML\Renderer\Format;


use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\Column\EscapingColumnDecorator;
use No3x\WPML\Renderer\Column\IColumn;
use No3x\WPML\Renderer\Column\SanitizedColumnDecorator;
use No3x\WPML\Renderer\WPML_ColumnManager;

class HTMLRenderer extends HeadingsRenderer {

    function renderColumn($item, $column_name) {
        /** @var IColumn $column_renderer */
        $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
        $column_format = ColumnFormat::FULL;
        if($this->outputIsHTMLItselfAndCantBeEncodedTherefore($column_name)) {
            $column_renderer = (new SanitizedColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
        }

        return $column_renderer->render($item, $column_format);
    }

    /**
     * @param $column_name
     * @return bool true if column prints html itself
     */
    protected function outputIsHTMLItselfAndCantBeEncodedTherefore($column_name) {
        return WPML_ColumnManager::COLUMN_MESSAGE === $column_name || WPML_ColumnManager::COLUMN_SUBJECT === $column_name || WPML_ColumnManager::COLUMN_ERROR === $column_name || WPML_ColumnManager::COLUMN_ATTACHMENTS === $column_name;
    }

    public function renderModal( $item ) {
        return $this->renderRawOrHtmlModal( $item, $item['message'] );
    }
}
