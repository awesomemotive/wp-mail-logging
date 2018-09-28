<?php

namespace No3x\WPML\Printer;

class EscapingColumnDecorator implements IColumn {

    /**
     * @var IColumn
     */
    private $column;

    public function __construct(IColumn $column) {
        $this->column = $column;
    }

    /**
     * @param array $mailArray
     * @param $format
     * @return mixed
     */
    public function render(array $mailArray, $format) {
        $delegated = $this->column->render($mailArray, $format);
        return htmlentities(htmlspecialchars_decode($delegated));
    }
}
