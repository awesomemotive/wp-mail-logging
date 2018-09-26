<?php

namespace No3x\WPML\Printer;

interface IColumn {

    /**
     * @param array $mailArray
     * @param $format
     * @return mixed
     */
    public function render(array $mailArray, $format);
}
