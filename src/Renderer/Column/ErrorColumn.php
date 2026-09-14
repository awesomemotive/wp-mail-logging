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

        // Use mb_strcut() instead of substr(): substr() cuts by raw bytes and can
        // split a multibyte UTF-8 character in half, producing an invalid string.
        // The caller (column_default()) passes this through esc_html(), which calls
        // wp_check_invalid_utf8() and returns '' for invalid UTF-8 -- so a mid-character
        // cut silently blanks the whole cell instead of just looking wrong.
        return mb_strcut( $item['error'], 0, self::MAX_ERROR_CHAR_LENGTH ) . '...';
    }

}
