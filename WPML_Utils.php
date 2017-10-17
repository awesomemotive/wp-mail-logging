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
     * Determines appropriate fa icon for a file
     * @sine 1.3
     * @param string $file_path path to file.
     * @return string returns the most suitable icon or generic one if not possible.
     */
    public static function determine_fa_icon( $file_path ) {
        $default_icon = '<i class="fa fa-file-o"></i>';
        $supported = array(
            'archive' => array(
                'application/zip',
                'application/x-rar-compressed',
                'application/x-rar',
                'application/x-gzip',
                'application/x-msdownload',
                'application/x-msdownload',
                'application/vnd.ms-cab-compressed',
            ),
            'audio',
            'code' => array(
                'text/x-c',
                'text/x-c++',
            ),
            'excel' => array( 'application/vnd.ms-excel'
            ),
            'image', 'text', 'movie', 'pdf', 'photo', 'picture',
            'powerpoint' => array(
                'application/vnd.ms-powerpoint'
            ), 'sound', 'video', 'word' => array(
                'application/msword'
            ), 'zip'
        );

        if( !function_exists('mime_content_type') ) {
            return $default_icon;
        }

        $mime = mime_content_type( $file_path );
        $mime_parts = explode( '/', $mime );
        $attribute = $mime_parts[0];
        $type = $mime_parts[1];

        $fa_icon = false;
        if ( ($key = self::recursive_array_search( $mime, $supported ) ) !== false ) {
            // Use specific icon for mime first.
            $fa_icon = $key;
        } elseif ( in_array( $attribute, $supported ) ) {
            // Use generic file icon.
            $fa_icon = $attribute;
        }

        if ( false === $fa_icon  ) {
            return $default_icon;
        } else {
            return '<i class="fa fa-file-' . $fa_icon . '-o"></i>';
        }
    }

    /**
     * Find appropriate fa icon from file path
     * @since 1.3
     * @param string $file_path path to file.
     * @return string
     */
    public static function generate_attachment_icon( $file_path ) {
        return self::determine_fa_icon( $file_path );
    }

    /**
     * Do a client side reload of the current page. Particularly useful to remove action query args.
     *
     * Before: http://example.com/wp-admin/admin.php?page=mypage&s=hello+world&_wpnonce=35ec405ae8&action=resend&paged=1&email%5B0%5D=126&email%5B1%5D=125&action2=-1
     * After: http://example.com/wp-admin/admin.php?page=mypage&s=hello+world
     * @param array $invalidArgs Optional. optional args to remove from url
     * @param array $additionalArgs Optional. args to add to the url
     */
    public static function clientSideReload( $invalidArgs, $additionalArgs = array() ) {

        $url = self::clear_url($invalidArgs, $additionalArgs);

        $string = '<script type="text/javascript">';
        $string .= 'window.location.replace("' . $url . '")';
        $string .= '</script>';
        echo $string;
        exit;
    }

    /**
     * Do a server side reload of the current page. Particularly useful to remove action query args.
     *

     * @param array $invalidArgs Optional. optional args to remove from url
     * @param array $additionalArgs Optional. args to add to the url
     */
    public static function serverSideReload( $invalidArgs = array(), $additionalArgs = array() ) {

        // Args to be removed necessarily
        $invalidArgs = array_merge($invalidArgs, array('_wp_http_referer'));

        $url = self::clear_url($invalidArgs, $additionalArgs);
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Builds a new url by removing duplicate args. Removing passed args by key. Adds passed new args.
     *
     * Before: http://example.com/wp-admin/admin.php?page=mypage&s=hello+world&_wpnonce=35ec405ae8&action=resend&paged=1&email%5B0%5D=126&email%5B1%5D=125&action2=-1
     * After: http://example.com/wp-admin/admin.php?page=mypage&s=hello+world
     * @param array $invalidArgs Optional. optional args to remove from url
     * @param array $additionalArgs Optional. args to add to the url
     * @return string
     */
    private static function clear_url( $invalidArgs, $additionalArgs = array() ) {

        $args = array();
        $vars = explode('&', $_SERVER['QUERY_STRING']);
        error_log(print_r($vars, true));
        if(!empty($vars)) {
            foreach($vars as $var) {
                $parts = explode('=', $var);

                $key = $parts[0];
                $val = $parts[1];

                // Catch "[" for url args like email[0]=x
                if( $pos = strpos($key, "%5B") | $pos = strpos($key, "[") ) {
                    $key = substr($key, 0, $pos);
                    $val = array($parts[1]);
                }

                if(!array_key_exists($key, $args) && !empty($val) && !in_array($key, $invalidArgs) ) {
                    $args[$key] = $val;
                } else {
                    error_log("removing key " . $key);
                    error_log("invalidArgs " . print_r($invalidArgs, true));
                }
            }
        }

        // Add new args
        $args = array_merge($args, $additionalArgs);

        // Get admin base url http://example.com/wp-admin/admin.php?s= to http://example.com/wp-admin/admin.php
        $base = mb_strstr($_SERVER['HTTP_REFERER'], '?', true);

        $url = add_query_arg( $args, $base );

        error_log('new ur: ' . $url);
        return $url;
    }
}
