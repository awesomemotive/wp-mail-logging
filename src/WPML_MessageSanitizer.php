<?php

namespace No3x\WPML;


class WPML_MessageSanitizer {

    const SAVED_COMMENT_HTMLEntity_OPEN = "savedcommenthtmlentityopen";
    const SAVED_COMMENT_HTMLEntity_CLOSE = "savedcommenthtmlentityclose";
    const SAVED_COMMENT_HTMLCode_OPEN = "savedcommenthtmlcodeopen";
    const SAVED_COMMENT_HTMLCode_CLOSE = "savedcommenthtmlcodeclose";

    private $mapping;
    private $buffer;

    public function __construct() {
        $this->mapping = [
            "&lt;!--" => '<' . self::SAVED_COMMENT_HTMLEntity_OPEN . '>',
            "--&gt;" => '<' . self::SAVED_COMMENT_HTMLEntity_CLOSE . '>',
            "<!--" => '<' . self::SAVED_COMMENT_HTMLCode_OPEN . '>',
            "-->" => '<' . self::SAVED_COMMENT_HTMLCode_CLOSE . '>',
        ];
    }

    public function sanitize($message) {
        $this->buffer = (string) $message;

        $this->stripStyleBlocks();
        $this->saveComments();
        $this->stripEvilCode();
        $this->recoverComments();

        return $this->buffer;
    }

    /**
     * Remove style elements and their CSS, preserving HTML comments.
     *
     * wp_kses() alone leaves CSS visible as text. Unclosed style blocks consume
     * the rest of their segment, matching browser parsing.
     *
     * Limitation: comment markers inside CSS can split a style block and leave
     * trailing CSS as inert text. Fully comment-wrapped styles are unaffected.
     *
     * @since 1.17.0
     * @since {VERSION} Preserves content when regex backtracking fails.
     *
     * @return void
     */
    private function stripStyleBlocks() {

        // Preserve comments at odd indices so style matching cannot cross their boundaries.
        $parts = preg_split( '#(<!--.*?-->)#s', $this->buffer, -1, PREG_SPLIT_DELIM_CAPTURE );

        // If splitting fails, strip styles from the whole buffer.
        if ( ! is_array( $parts ) ) {
            $parts = [ $this->buffer ];
        }

        foreach ( $parts as $i => $part ) {

            if ( $i % 2 === 1 ) {
                continue;
            }

            // Remove closed blocks, then any unclosed block through the segment's end.
            $patterns = [
                '#<style\b[^>]*>.*?</style>#is',
                '#<style\b[^>]*>.*$#is',
            ];

            foreach ( $patterns as $pattern ) {
                $stripped = preg_replace( $pattern, '', $part );

                // Keep the segment on regex failure. The next pattern may remove
                // everything from <style> onward; if both fail, stripEvilCode()
                // removes the tags and leaves inert CSS text.
                if ( $stripped !== null ) {
                    $part = $stripped;
                }
            }

            $parts[ $i ] = $part;
        }

        $this->buffer = implode( '', $parts );
    }

    private function saveComments() {
        $this->swapCommentsInStringWithMapping($this->mapping);
    }

    private function recoverComments() {
        $this->swapCommentsInStringWithMapping(array_flip($this->mapping));
    }

    private function swapCommentsInStringWithMapping($mapping) {
        foreach ($mapping as $from => $to) {
            $this->buffer = str_replace($from, $to , $this->buffer);
        }
    }

    private function stripEvilCode() {
        $allowed_tags = wp_kses_allowed_html( 'post' );

        if ( ! is_array( $allowed_tags ) ) {
            $allowed_tags = [];
        }

        $allowed_tags[self::SAVED_COMMENT_HTMLEntity_OPEN][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLEntity_CLOSE][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLCode_OPEN][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLCode_CLOSE][''] = true;

        // Strip navigation attributes from both links and image-map areas.
        unset(
            $allowed_tags['a']['target'],
            $allowed_tags['a']['rel'],
            $allowed_tags['area']['target'],
            $allowed_tags['area']['rel']
        );

        $this->buffer = wp_kses( $this->buffer, $allowed_tags );
    }

}
