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
