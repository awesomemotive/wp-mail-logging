<?php

namespace No3x\WPML\Renderer\Format;


use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\Column\EscapingColumnDecorator;
use No3x\WPML\Renderer\Column\IColumn;
use No3x\WPML\Renderer\WPML_ColumnManager;

class RawRenderer extends HeadingsRenderer {

    function renderColumn($item, $column_name) {
        /** @var IColumn $column_renderer */
        $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
        $column_format = ColumnFormat::FULL;
        if ($column_name === WPML_ColumnManager::COLUMN_ERROR || $column_name == WPML_ColumnManager::COLUMN_ATTACHMENTS) {
            $column_format = ColumnFormat::SIMPLE;
        }

        return $column_renderer->render($item, $column_format);
    }
}
