<?php

namespace No3x\WPML;

/**
 * Class for interacting with hooks
 * The code is from Nathan Johnson on SO but slightly modified.
 * I found that the namespace is part of the callback and there is no need to consider or remove it.
 * https://wordpress.stackexchange.com/a/258767/33141
 */
class WPML_Hook_Remover {

    /**
     * Remove a hook from the $wp_filter global
     *
     * @param string   $tag      The hook which the callback is attached to
     * @param callable $callback The callback to remove
     * @param int      $priority The priority of the callback
     *
     * @access public
     * @since 1.8.5
     *
     * @return bool Whether the filter was originally in the $wp_filter global
     */
    public function remove_hook( $tag, $callback, $priority = 10 ) {
        global $wp_filter;
        $tag_hooks = $wp_filter[ $tag ]->callbacks[ $priority ];
        foreach ( $tag_hooks as $the_tag => $the_callback ) {
            if( $this->parse_callback( $the_callback ) === $callback ) {
                return \remove_filter( $tag, $the_callback[ 'function' ], $priority );
            }
        }
        return \remove_filter( $tag, $callback, $priority );
    }

    /**
     * Get the class name of an object
     *
     * @param object $object
     *
     * @access protected
     * @since 1.8.5
     *
     * @return string
     */
    protected function get_class( $object ) {
        return get_class( $object );
    }

    /**
     * Return the callback object
     *
     * @param array $callback
     *
     * @access protected
     * @since 1.8.5
     *
     * @return object
     */
    protected function callback_object( $callback ) {
        return $callback[ 'function' ][ 0 ];
    }

    /**
     * Return the callback method
     *
     * @param array $callback
     *
     * @access protected
     * @since 1.8.5
     *
     * @return string
     */
    protected function callback_method( $callback ) {
        return $callback[ 'function' ][ 1 ];
    }

    /**
     * Return the class from the callback
     *
     * @param array $callback
     *
     * @access protected
     * @since 1.8.5
     *
     * @return string
     */
    protected function get_class_from_callback( $callback ) {
        return $this->get_class( $this->callback_object( $callback ) );
    }

    /**
     * Parse the callback into an array
     *
     * @param array $callback
     *
     * @access protected
     * @since 1.8.5
     *
     * @return array|bool
     */
    protected function parse_callback( $callback ) {
        return is_array( $callback[ 'function' ] ) ?
            [ $this->classFor( $callback ), $this->method( $callback ) ] : false;
    }

    /**
     * Return the class of a callback
     *
     * @param array $callback
     *
     * @access protected
     * @since 1.8.5
     *
     * @return string
     */
    protected function classFor( $callback ) {
        return $this->get_class_from_callback( $callback );
    }

    /**
     * Return the method of a callback
     *
     * @param array $callback
     *
     * @access protected
     * @since 1.8.5
     *
     * @return string
     */
    protected function method( $callback ) {
        return $callback[ 'function' ][ 1 ];
    }
}
