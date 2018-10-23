<?php

namespace No3x\WPML\Renderer\Column;


class ErrorColumn extends GenericColumn {

    /**
     * TimestampColumn constructor.
     */
    public function __construct() {
        parent::__construct("error");
    }

    /**
     * @inheritdoc
     */
    public function render(array $mailArray, $format) {
        if($format == ColumnFormat::SIMPLE) {
            return parent::render($mailArray, $format);
        } elseif ($format == ColumnFormat::FULL) {
            return $this->error_column($mailArray);
        }
        throw new \Exception("Unknown Format");
    }

    /**
     * Renders the error column.
     * @since 1.8.0
     * @param $item
     * @return string
     */
    function error_column($item) {
        $error = $item['error'];
        if( empty($error)) return "";
        return '<i class="fa fa-exclamation-circle" title="' . esc_attr( $error ) . '"></i>';
    }

}
