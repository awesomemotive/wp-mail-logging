<?php

namespace No3x\WPML\Renderer\Column;

class EscapingColumnDecorator implements IColumn {

    /**
     * @var IColumn
     */
    private $column;

    public function __construct(IColumn $column) {
        $this->column = $column;
    }

    /**
     * @inheritdoc
     */
    public function render(array $mailArray, $format) {
        $delegated = $this->column->render($mailArray, $format);

        if ( ! isset( $delegated ) ) {
            return null;
        }

        return htmlentities(htmlspecialchars_decode($delegated));
    }
}
