<?php

namespace No3x\WPML\Renderer;

/**
 * Detects whether an email body references sub-resources that the preview's
 * Content Security Policy blocks while remote content is turned off.
 *
 * The preview iframe is served with `img-src data:` and `font-src data:` until the
 * viewer opts in, at which point both widen to `https: data:`. Anything that is not
 * an inline `data:` URI is therefore gated behind that opt-in -- relative URLs
 * included, since they resolve to same-origin https URLs rather than to `data:`.
 * This class answers the one question the preview UI needs: is there anything in
 * this message for the "Load images" button to unblock?
 *
 * Where the answer is ambiguous it returns true. A false negative leaves the viewer
 * with no way to load a genuinely blocked image; a false positive is only a button
 * that does nothing.
 *
 * @since {VERSION}
 */
class RemoteContentDetector {

    /**
     * Whether the message references anything the preview CSP blocks.
     *
     * @since {VERSION}
     * @access public
     *
     * @param string $message Raw email body as it was logged.
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
     * Remove the references to remote sub-resources from already-filtered markup.
     *
     * The preview normally blocks these with its Content Security Policy and leaves the
     * markup alone. When the response could not send that header, this takes the
     * references out instead, so the "Load images" opt-in still decides whether anything
     * is fetched. Expects the output of `EmailLogsTab::get_html_preview_message()`, not a
     * raw body -- `wp_kses()` has already run by then.
     *
     * Covers exactly what `has_blocked_content()` detects: `img` sources and CSS `url()`
     * references. Keeping the two symmetric means the notice and the stripping can never
     * disagree about what counts as remote.
     *
     * @since {VERSION}
     * @access public
     *
     * @param string $message Filtered preview markup.
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
     * Drop `src` and `srcset` from images pointing at blocked resources.
     *
     * An `img` with neither attribute requests nothing, so the element stays in place
     * and the layout it occupies does not collapse.
     *
     * @since {VERSION}
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
     * Point blocked CSS `url()` references at an empty `data:` URI.
     *
     * Covers `<style>` blocks and inline `style` attributes, the same two places
     * `get_css_text()` reads. `data:,` is a valid empty URI, so the declaration stays
     * syntactically intact and nothing is fetched.
     *
     * @since {VERSION}
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
     * Replace every blocked `url()` in a fragment of CSS.
     *
     * @since {VERSION}
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
     * Remove one attribute from a tag.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $tag       Full tag, angle brackets included.
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
     * Remove the markup the preview itself discards before rendering.
     *
     * Keeps commented-out and Office `<xml>` payloads from lighting up the notice for
     * images the preview never renders. Mirrors `EmailLogsTab::get_html_preview_message()`,
     * plus the comment stripping `wp_kses()` performs on top of it.
     *
     * @since {VERSION}
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

            // preg_replace() returns null when it hits a backtrack limit, which a very
            // large body can do. Keep the un-stripped text rather than losing the message.
            if ( $stripped !== null ) {
                $message = $stripped;
            }
        }

        return $message;
    }

    /**
     * Whether any `<img>` points at a resource the CSP blocks.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $message Email body with hidden markup already removed.
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
     * Whether any candidate in a `srcset` points at a resource the CSP blocks.
     *
     * Candidates are separated by commas, but a `data:` URI contains commas of its own,
     * so the list is tokenised on whitespace instead -- a srcset URL can never contain
     * any. What is left is either a URL or a width/pixel-density descriptor.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $srcset Value of the `srcset` attribute.
     *
     * @return bool
     */
    private static function has_blocked_srcset_url( $srcset ) {

        foreach ( preg_split( '/\s+/', $srcset, -1, PREG_SPLIT_NO_EMPTY ) as $token ) {

            $token = rtrim( $token, ',' );

            // Skip `2x` and `640w` descriptors; everything else is a candidate URL.
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
     * Whether any CSS `url()` points at a resource the CSP blocks.
     *
     * Covers `background-image` and `@font-face` alike, in both `<style>` blocks and
     * inline `style` attributes. Only those two places are searched, so prose that
     * happens to contain "url(" does not trigger the notice.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $message Email body with hidden markup already removed.
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
     * Collect every piece of CSS the preview renders.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $message Email body with hidden markup already removed.
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
     * Read a single attribute out of a tag.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $tag       Full tag, angle brackets included.
     * @param string $attribute Attribute name to read.
     *
     * @return string Attribute value, or an empty string when the attribute is absent.
     */
    private static function get_attribute_value( $tag, $attribute ) {

        $pattern = '/\b' . preg_quote( $attribute, '/' ) . '\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i';

        if ( ! preg_match( $pattern, $tag, $match ) ) {
            return '';
        }

        return trim( $match[1], "\"'" );
    }

    /**
     * Whether a single URL is one the preview CSP blocks.
     *
     * @since {VERSION}
     * @access private
     *
     * @param string $url URL as it appears in the markup.
     *
     * @return bool
     */
    private static function is_blocked_url( $url ) {

        $url = trim( $url );

        if ( $url === '' ) {
            return false;
        }

        // `data:` renders whether or not remote content is allowed, and `cid:` refers to
        // an attachment the preview cannot resolve in either mode. Everything else --
        // absolute, protocol-relative or relative -- is gated behind the opt-in.
        return ! preg_match( '/^(?:data|cid):/i', $url );
    }
}
