<?php

namespace No3x\WPML\Printer;

class WPML_ColumnRenderer {

    /**
     * WPML_ColumnRenderer constructor.
     */
    public function __construct() {
    }

    public function getColumn($column_name) {
        switch ($column_name) {
            case 'timestamp':
                return new TimestampColumn();
            case 'attachments':
                return new AttachmentsColumn();
            case 'error':
                return new ErrorColumn();
            default:
                return new GenericColumn($column_name);
        }
    }
}
