<?php
/**
 * User: No3x
 * Date: 06.09.15
 * Time: 12:47
 */

namespace No3x\WPML;
use No3x\WPML\Pimple\Container;

class WPML_DI_Container extends Container {

    public function addActionsAndFilters() {
        foreach ( $this->keys() as $key ) {
            $content = $this[ $key ];
            if ( is_object( $content ) ) {
                $reflection = new \ReflectionClass( $content );
                if ( $reflection->hasMethod( 'addActionsAndFilters' ) ) {
                    $content->addActionsAndFilters();
                }
            }
        }
    }
}
