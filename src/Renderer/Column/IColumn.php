<?php

namespace No3x\WPML\Renderer\Column;

interface IColumn {

    /**
     * @param array $mailArray
     * @param $format
     * @return mixed Unescaped value; callers must escape for output.
     * @throws \Exception
     */
    public function render(array $mailArray, $format);
}
