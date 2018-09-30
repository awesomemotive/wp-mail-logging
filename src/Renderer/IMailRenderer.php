<?php

namespace No3x\WPML\Renderer;


interface IMailRenderer {
    /**
     * @param $item
     * @return string|array
     * @throws \Exception
     */
    function render($item);
}
