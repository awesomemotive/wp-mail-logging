<?php

namespace No3x\WPML\Renderer\Column;


class ErrorColumn extends GenericColumn {

    /**
     * Max number of character to display before we
     * truncate with ellipsis.
     *
     * @var int
     */
    const MAX_ERROR_CHAR_LENGTH = 90;

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

        if ( empty( $item['error'] ) ) {
            return '';
        }

        if ( strlen( $item['error'] ) <= self::MAX_ERROR_CHAR_LENGTH ) {
            return $item['error'];
        }

        echo substr( $item['error'], 0, self::MAX_ERROR_CHAR_LENGTH ) . '...';
    }

}
