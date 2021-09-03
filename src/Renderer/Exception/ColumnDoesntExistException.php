<?php

namespace No3x\WPML\Renderer\Exception;

use \Exception;

class ColumnDoesntExistException extends Exception {

    const MESSAGE = "'%s' doesn't exist but it should";

    public function __construct($column_name) {
        parent::__construct(sprintf(self::MESSAGE, $column_name));
    }

    public static function get_class() {
        return __CLASS__;
    }
}
