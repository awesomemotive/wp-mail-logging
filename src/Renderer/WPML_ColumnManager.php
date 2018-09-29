<?php

namespace No3x\WPML\Renderer;

use No3x\WPML\Renderer\Column\AttachmentsColumn;
use No3x\WPML\Renderer\Column\ErrorColumn;
use No3x\WPML\Renderer\Column\GenericColumn;
use No3x\WPML\Renderer\Column\IColumn;
use No3x\WPML\Renderer\Column\TimestampColumn;

class WPML_ColumnManager {

    const COLUMN_MAIL_ID        = 'mail_id';
    const COLUMN_TIMESTAMP		= 'timestamp';
    const COLUMN_HOST           = 'host';
    const COLUMN_RECEIVER		= 'receiver';
    const COLUMN_SUBJECT		= 'subject';
    const COLUMN_MESSAGE		= 'message';
    const COLUMN_HEADERS		= 'headers';
    const COLUMN_ATTACHMENTS	= 'attachments';
    const COLUMN_ERROR		    = 'error';
    const COLUMN_PLUGIN_VERSION = 'plugin_version';

    private $columns;

    /**
     * WPML_ColumnRenderer constructor.
     */
    public function __construct() {
        $this->columns = [
            self::COLUMN_MAIL_ID	    => __( 'ID', 'wp-mail-logging' ),
            self::COLUMN_TIMESTAMP		=> __( 'Time', 'wp-mail-logging' ),
            self::COLUMN_HOST           => __( 'Host', 'wp-mail-logging' ),
            self::COLUMN_RECEIVER		=> __( 'Receiver', 'wp-mail-logging' ),
            self::COLUMN_SUBJECT		=> __( 'Subject', 'wp-mail-logging' ),
            self::COLUMN_MESSAGE		=> __( 'Message', 'wp-mail-logging' ),
            self::COLUMN_HEADERS		=> __( 'Headers', 'wp-mail-logging' ),
            self::COLUMN_ATTACHMENTS	=> __( 'Attachments', 'wp-mail-logging' ),
            self::COLUMN_ERROR          => __( 'Error', 'wp-mail-logging' ),
            self::COLUMN_PLUGIN_VERSION	=> __( 'Plugin Version', 'wp-mail-logging' ),
        ];
    }

    /**
     * @param $column_name
     * @return IColumn
     */
    public function getColumnRenderer($column_name) {
        switch ($column_name) {
            case self::COLUMN_TIMESTAMP:
                return new TimestampColumn();
            case self::COLUMN_ATTACHMENTS:
                return new AttachmentsColumn();
            case self::COLUMN_ERROR:
                return new ErrorColumn();
            default:
                return new GenericColumn($column_name);
        }
    }

    public function getColumns() {
        return $this->columns;
    }

    public function getColumnNames() {
        return array_keys($this->columns);
    }

    public function getTranslationForColumn($column_name) {
        return $this->getColumns()[$column_name];
    }
}
