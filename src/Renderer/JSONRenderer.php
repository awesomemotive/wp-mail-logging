<?php

namespace No3x\WPML\Renderer;


use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\Column\EscapingColumnDecorator;

class JSONRenderer extends BaseRenderer {

    function render($item) {
        $json = [];
        foreach ($item as $column_name => $value) {
            $column_renderer = (new EscapingColumnDecorator($this->columnManager->getColumnRenderer($column_name)));
            if ($column_name !== WPML_ColumnManager::COLUMN_ERROR && $column_name !== WPML_ColumnManager::COLUMN_ATTACHMENTS) {
                $column_format = ColumnFormat::FULL;
            } else {
                $column_format = ColumnFormat::SIMPLE;
            }
            $json[$column_name] = $column_renderer->render($item, $column_format);
        }

        return $json;
    }
}
