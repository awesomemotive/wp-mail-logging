<?php

namespace No3x\WPML;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utils
 * @author No3x
 * @since 1.6.0
 */
class WPML_Utils {
    /**
     * Ensure value is subset of given set
     * @since 1.6.0
     * @param string $value expected value.
     * @param array  $allowed_values allowed values.
     * @param string $default_value default value.
     * @return mixed
     */
    public static function sanitize_expected_value( $value, $allowed_values, $default_value = null ) {
        $allowed_values = (is_array( $allowed_values ) ) ? $allowed_values : array( $allowed_values );
        if ( $value && in_array( $value, $allowed_values ) ) {
            return $value;
        }
        if ( null !== $default_value ) {
            return $default_value;
        }
        return false;
    }

    /**
     * Multilevel array_search
     * @since 1.3
     * @param string $needle the searched value.
     * @param array  $haystack the array.
     * @return mixed Returns the value if needle is found in the array, false otherwise.
     * @see array_search()
     */
    public static function recursive_array_search( $needle, $haystack ) {
        foreach ( $haystack as $key => $value ) {
            $current_key = $key;
            if ( $needle === $value or ( is_array( $value ) && self::recursive_array_search( $needle, $value ) !== false ) ) {
                return $current_key;
            }
        }
        return false;
    }

    /**
     * Determines appropriate fa icon for a given icon class
     * @since 1.9.0
     * @param string $iconClass icon class.
     * @return string returns fa icon.
     */
    public static function determine_fa_icon( $iconClass ) {
        return '<i class="fa ' . esc_attr($iconClass == "file" ? "fa-file-o" : "fa-file-{$iconClass}-o") . '"></i>';
    }

    /**
     * Find appropriate fa icon from file path
     * @since 1.9.0
     * @param WPML_Attachment $attachment attachment.
     * @return string
     */
    public static function generate_attachment_icon( $attachment ) {
        return self::determine_fa_icon( $attachment->getIconClass() );
    }
}
