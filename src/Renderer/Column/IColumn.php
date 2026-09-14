<?php

namespace No3x\WPML\Renderer\Column;

interface IColumn {

    /**
     * @param array $mailArray
     * @param $format
     * @return mixed Plain, unescaped value; callers are responsible for escaping it before output.
     * @throws \Exception
     */
    public function render(array $mailArray, $format);
}
