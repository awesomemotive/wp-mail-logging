<?php

namespace No3x\WPML\Renderer\Column;


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
     */
    public function render(array $mailArray, $format) {
        $delegated = $this->column->render($mailArray, $format);
        return $this->messageSanitizer->sanitize($delegated);
    }
}
