<?php

namespace No3x\WPML\Renderer\Column;


class TimestampColumn extends GenericColumn {

    /**
     * TimestampColumn constructor.
     */
    public function __construct() {
        parent::__construct("timestamp");
    }

    /**
     * @inheritdoc
     */
    public function render(array $mailArray, $format) {
        $rendered = parent::render($mailArray, $format);
        return date_i18n( apply_filters( 'wpml_get_date_time_format', '' ), strtotime( $rendered ) );
    }
}
