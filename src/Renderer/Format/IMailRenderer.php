<?php

namespace No3x\WPML\Renderer\Format;


interface IMailRenderer {
    /**
     * @param $item
     * @return string|array
     * @throws \Exception
     */
    function render($item);

    function renderModal( $item );
}
