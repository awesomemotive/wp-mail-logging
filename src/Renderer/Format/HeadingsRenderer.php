<?php

namespace No3x\WPML\Renderer\Format;

abstract class HeadingsRenderer extends BaseRenderer {

    function render($item) {

        $buffer = '';
        foreach ($item as $column_name => $value) {
            if( $this->shouldRenderThisColumn($column_name) ) {
                $buffer .= $this->buildOutputForThisColumn($item, $column_name);
            }
        }

        return $buffer;
    }

    private function getTranslation($column_name) {
        return $this->columnManager->getTranslationForColumn($column_name);
    }

    private function shouldRenderThisColumn($column_name) {
        return !in_array($column_name, $this->getHiddenColumns());
    }

    protected function buildOutputForThisColumn($item, $column_name) {
        $title = "<span class=\"title\">{$this->getTranslation($column_name)}: </span>";
        $content = $this->renderColumn($item, $column_name);
        return $title . $content;
    }

    abstract function renderColumn($item, $column_name);
}
