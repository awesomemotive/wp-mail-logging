<?php

namespace No3x\WPML;
use No3x\WPML\Pimple\Container;

use \ReflectionClass;

class WPML_DI_Container extends Container {

    public function addActionsAndFilters() {
        foreach ($this->keys() as $key) {
            $content = $this[$key];
            if (is_object($content)) {
                $reflection = new ReflectionClass($content);
                if( $this->hasMethod($reflection) ) {
                    if( !$this->implementsInterface($reflection) ) {
                        $this->log($content);
                    }
                    /** @var $content IHooks */
                    $content->addActionsAndFilters();
                }
            }
        }
    }

    private function implementsInterface(ReflectionClass $reflection) {
        return ($reflection->implementsInterface('No3x\WPML\IHooks'));
    }

    private function hasMethod(ReflectionClass $reflection) {
        return ($reflection->hasMethod('addActionsAndFilters'));
    }

    private function log($object) {
        $class_name = get_class($object);
        error_log("{$class_name} doesn't implement the IHook Interface but it seems like it should. Did you forget to add the implements statement?");
    }
}
