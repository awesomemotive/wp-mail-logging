<?php

namespace No3x\WPML\Renderer\Column;

use No3x\WPML\Renderer\WPML_ColumnManager;
use No3x\WPML\WPML_MessageSanitizer;

class SanitizedColumnDecorator implements IColumn {

    /**
     * @var IColumn
     */
    private $column;

    /**
     * ColumnSanitizer constructor.
     */
    private $messageSanitizer;

    public function __construct(IColumn $column) {
        $this->column = $column;
        $this->messageSanitizer = new WPML_MessageSanitizer();
    }

    /**
     * @inheritdoc
     *
     * @since 1.15.0 Used `esc_html()` on `receiver` column.
     */
    public function render(array $mailArray, $format) {
        $delegated = $this->column->render($mailArray, $format);

        if (
            method_exists( $this->column, 'getColumnName' ) &&
            $this->column->getColumnName() === WPML_ColumnManager::COLUMN_RECEIVER
        ) {
            return esc_html( $delegated );
        }

        return $this->messageSanitizer->sanitize($delegated);
    }
}
