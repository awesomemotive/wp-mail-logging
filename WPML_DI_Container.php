<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 06.09.15
 * Time: 12:47
 */

namespace No3x\WPML;
use \Pimple\Container;

class WPML_DI_Container extends Container {

	public function run() {
		foreach( $this->keys() as $key ) {
			$content = $this[$key];
			if( is_object( $content ) ) {
				$reflection = new \ReflectionClass( $content );
				if( $reflection->hasMethod('addActionsAndFilters') ) {
					$content->addActionsAndFilters();
				}
			}
		}
	}
} 