<?php

namespace No3x\WPML\Renderer\Column;

use No3x\WPML\Renderer\Exception\ColumnDoesntExistException;

class GenericColumn implements IColumn {
    protected $column_name;

    /**
     * GenericColumn constructor.
     * @param $column_name
     */
    public function __construct($column_name) {
        $this->column_name = $column_name;
    }

    /**
     * @inheritdoc
     */
    public function render(array $mailArray, $format) {
        if( ! array_key_exists($this->column_name, $mailArray) ) {
            throw new ColumnDoesntExistException($this->column_name);
        }

        return $mailArray[$this->column_name];
    }
}
