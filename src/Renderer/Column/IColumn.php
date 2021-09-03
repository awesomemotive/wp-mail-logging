<?php

namespace No3x\WPML\Renderer\Column;

interface IColumn {

    /**
     * @param array $mailArray
     * @param $format
     * @return mixed
     * @throws \Exception
     */
    public function render(array $mailArray, $format);
}
