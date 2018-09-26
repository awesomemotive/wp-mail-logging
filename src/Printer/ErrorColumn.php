<?php

namespace No3x\WPML\Printer;


class ErrorColumn extends GenericColumn {

    /**
     * TimestampColumn constructor.
     */
    public function __construct() {
        parent::__construct("error");
    }

    /**
     * @inheritdoc
     * @throws Exception\ColumnDoesntExistException
     */
    public function render(array $mailArray, $format) {
        if($format == ColumnFormat::SIMPLE) {
            return parent::render($mailArray, $format);
        } elseif ($format == ColumnFormat::FULL) {
            return $this->column_overridden_error($mailArray);
        }
        throw new \Exception("Unknown Format");
    }

    /**
     * Renders the error column.
     * @since 1.8.0
     * @param $item
     * @return string
     */
    function column_overridden_error($item) {
        $error = $item['error'];
        if( empty($error)) return "";
        return '<i class="fa fa-exclamation-circle" title="' . esc_attr( $error ) . '"></i>';
    }

}
