<?php

namespace No3x\WPML\Renderer;

/**
 * Detects resources blocked by the preview's Content Security Policy (CSP).
 *
 * Images and fonts allow only `data:` until opt-in adds `https:`. Relative URLs
 * also require opt-in. Ambiguous URLs count as blocked to keep "Load images" available.
 *
 * @since 1.17.0
 */
class RemoteContentDetector {

    /**
     * Check for blocked image or CSS URLs.
     *
     * @since 1.17.0
     * @access public
     *
     * @param string $message Raw email body.
     *
     * @return bool
     */
    public static function has_blocked_content( $message ) {

        if ( ! is_string( $message ) || $message === '' ) {
            return false;
        }

        $message = self::strip_hidden_markup( $message );

        return self::has_blocked_image( $message ) || self::has_blocked_css_url( $message );
    }

    /**
     * Remove blocked image and CSS URLs when the CSP header cannot be sent.
     *
     * Uses the same URL checks as `has_blocked_content()` to match the opt-in notice.
     *
     * @since 1.17.0
     * @access public
     *
     * @param string $message Preview markup after `EmailLogsTab::get_html_preview_message()`.
     *
     * @return string Markup with blocked sources removed.
     */
    public static function strip_remote_content( $message ) {

        if ( ! is_string( $message ) || $message === '' ) {
            return $message;
        }

        return self::strip_blocked_css_urls( self::strip_blocked_image_sources( $message ) );
    }

    /**
     * Remove blocked image `src` and `srcset` attributes.
     *
     * Keep image elements to preserve layout.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $message Filtered preview markup.
     *
     * @return string
     */
    private static function strip_blocked_image_sources( $message ) {

        $stripped = preg_replace_callback(
            '/<img\b[^>]*>/i',
            function ( $match ) {

                $tag = $match[0];

                foreach ( [ 'src', 'srcset' ] as $attribute ) {

                    $value = self::get_attribute_value( $tag, $attribute );

                    if ( $value === '' ) {
                        continue;
                    }

                    $blocked = $attribute === 'srcset'
                        ? self::has_blocked_srcset_url( $value )
                        : self::is_blocked_url( $value );

                    if ( $blocked ) {
                        $tag = self::remove_attribute( $tag, $attribute );
                    }
                }

                return $tag;
            },
            $message
        );

        return $stripped === null ? $message : $stripped;
    }

    /**
     * Replace blocked CSS URLs with `data:,` in style blocks and attributes.
     *
     * The empty data URI preserves CSS syntax without fetching a resource.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $message Filtered preview markup.
     *
     * @return string
     */
    private static function strip_blocked_css_urls( $message ) {

        $stripped = preg_replace_callback(
            '#(<style\b[^>]*>)(.*?)(</style>)#is',
            function ( $match ) {
                return $match[1] . self::neutralize_css_urls( $match[2] ) . $match[3];
            },
            $message
        );

        if ( $stripped !== null ) {
            $message = $stripped;
        }

        $stripped = preg_replace_callback(
            '/\bstyle\s*=\s*(["\'])(.*?)\1/is',
            function ( $match ) {
                return 'style=' . $match[1] . self::neutralize_css_urls( $match[2] ) . $match[1];
            },
            $message
        );

        return $stripped === null ? $message : $stripped;
    }

    /**
     * Replace blocked CSS `url()` values with an empty data URI.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $css CSS text.
     *
     * @return string
     */
    private static function neutralize_css_urls( $css ) {

        $stripped = preg_replace_callback(
            '/url\(\s*("[^"]*"|\'[^\']*\'|[^)]*)\s*\)/i',
            function ( $match ) {

                $url = trim( $match[1], " \t\n\r\0\x0B\"'" );

                return self::is_blocked_url( $url ) ? 'url(data:,)' : $match[0];
            },
            $css
        );

        return $stripped === null ? $css : $stripped;
    }

    /**
     * Remove a tag attribute.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $tag       Tag including angle brackets.
     * @param string $attribute Attribute name to remove.
     *
     * @return string
     */
    private static function remove_attribute( $tag, $attribute ) {

        $pattern = '/\s*\b' . preg_quote( $attribute, '/' ) . '\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i';

        $stripped = preg_replace( $pattern, '', $tag );

        return $stripped === null ? $tag : $stripped;
    }

    /**
     * Remove comments and Office XML ignored by the preview.
     *
     * Mirrors `EmailLogsTab::get_html_preview_message()` and `wp_kses()` comment removal.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $message Raw email body.
     *
     * @return string
     */
    private static function strip_hidden_markup( $message ) {

        $patterns = [
            '/<xml\b[^>]*>(.*?)<\/xml>/is',
            '/<!--(.*?)-->/s',
        ];

        foreach ( $patterns as $pattern ) {
            $stripped = preg_replace( $pattern, '', $message );

            // Preserve the message if regex backtracking fails.
            if ( $stripped !== null ) {
                $message = $stripped;
            }
        }

        return $message;
    }

    /**
     * Check images for blocked URLs.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $message Email body without hidden markup.
     *
     * @return bool
     */
    private static function has_blocked_image( $message ) {

        if ( ! preg_match_all( '/<img\b[^>]*>/i', $message, $tags ) ) {
            return false;
        }

        foreach ( $tags[0] as $tag ) {

            if ( self::is_blocked_url( self::get_attribute_value( $tag, 'src' ) ) ) {
                return true;
            }

            if ( self::has_blocked_srcset_url( self::get_attribute_value( $tag, 'srcset' ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check `srcset` candidates for blocked URLs.
     *
     * Split on whitespace to preserve commas inside data URIs.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $srcset Image source candidates.
     *
     * @return bool
     */
    private static function has_blocked_srcset_url( $srcset ) {

        foreach ( preg_split( '/\s+/', $srcset, -1, PREG_SPLIT_NO_EMPTY ) as $token ) {

            $token = rtrim( $token, ',' );

            // Skip width and pixel-density descriptors, such as `640w` and `2x`.
            if ( $token === '' || preg_match( '/^\d+(?:\.\d+)?[wx]$/i', $token ) ) {
                continue;
            }

            if ( self::is_blocked_url( $token ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check CSS `url()` values for blocked resources.
     *
     * Search only style blocks and attributes to avoid matching email text.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $message Email body without hidden markup.
     *
     * @return bool
     */
    private static function has_blocked_css_url( $message ) {

        $css = self::get_css_text( $message );

        if ( $css === '' || ! preg_match_all( '/url\(\s*("[^"]*"|\'[^\']*\'|[^)]*)\s*\)/i', $css, $matches ) ) {
            return false;
        }

        foreach ( $matches[1] as $url ) {

            if ( self::is_blocked_url( trim( $url, " \t\n\r\0\x0B\"'" ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect CSS from style blocks and attributes.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $message Email body without hidden markup.
     *
     * @return string
     */
    private static function get_css_text( $message ) {

        $css = '';

        if ( preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', $message, $blocks ) ) {
            $css .= implode( ' ', $blocks[1] );
        }

        if ( preg_match_all( '/\bstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $message, $attributes ) ) {

            foreach ( $attributes[1] as $value ) {
                $css .= ' ' . trim( $value, "\"'" );
            }
        }

        return $css;
    }

    /**
     * Read a tag attribute.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $tag       Tag including angle brackets.
     * @param string $attribute Attribute name to read.
     *
     * @return string Attribute value, or an empty string if absent.
     */
    private static function get_attribute_value( $tag, $attribute ) {

        $pattern = '/\b' . preg_quote( $attribute, '/' ) . '\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i';

        if ( ! preg_match( $pattern, $tag, $match ) ) {
            return '';
        }

        return trim( $match[1], "\"'" );
    }

    /**
     * Check whether a URL requires remote-content opt-in.
     *
     * @since 1.17.0
     * @access private
     *
     * @param string $url URL from the markup.
     *
     * @return bool
     */
    private static function is_blocked_url( $url ) {

        $url = trim( $url );

        if ( $url === '' ) {
            return false;
        }

        // Data URIs always render; CID attachments never resolve. All other URLs require opt-in.
        return ! preg_match( '/^(?:data|cid):/i', $url );
    }
}
