<?php

namespace No3x\WPML\Renderer\Format;


use No3x\WPML\Renderer\WPML_ColumnManager;

abstract class BaseRenderer implements IMailRenderer {

    /** @var WPML_ColumnManager */
    protected $columnManager;

    /**
     * BaseRenderer constructor.
     * @param WPML_ColumnManager $columnManager
     */
    public function __construct(WPML_ColumnManager $columnManager) {
        $this->columnManager = $columnManager;
    }

    abstract function render($item);

    protected function getHiddenColumns() {
        return [
            WPML_ColumnManager::COLUMN_MAIL_ID,
            WPML_ColumnManager::COLUMN_PLUGIN_VERSION
        ];
    }

}
