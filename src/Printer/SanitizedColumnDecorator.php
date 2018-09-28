<?php
/**
 * Created by IntelliJ IDEA.
 * User: noex_
 * Date: 28.09.2018
 * Time: 23:06
 */

namespace No3x\WPML\Printer;


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
     * @param array $mailArray
     * @param $format
     * @return mixed
     */
    public function render(array $mailArray, $format) {
        $delegated = $this->column->render($mailArray, $format);
        return $this->messageSanitizer->sanitize($delegated);
    }
}
