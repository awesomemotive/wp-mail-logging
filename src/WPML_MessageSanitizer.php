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
        $this->buffer = $message;

        $this->stripStyleBlocks();
        $this->saveComments();
        $this->stripEvilCode();
        $this->recoverComments();

        return $this->buffer;
    }

    /**
     * Remove style elements along with their contents.
     *
     * wp_kses() strips the tags but keeps the CSS between them, which would
     * render as visible text. This path feeds the admin document, so the
     * element is removed entirely rather than allowed. An unterminated
     * `<style>` takes everything to the end of its segment with it, matching
     * how a browser would parse it. The buffer is first split on HTML
     * comments so a literal `<style` sitting inside comment content cannot
     * pair with a real closing tag elsewhere and consume the comment markers
     * in between; comment contents are left untouched since they are not
     * rendered.
     *
     * Known limitation: a "<!--" occurring inside a <style> element is not
     * really a comment delimiter there, but this split treats it as one, so
     * the element is torn in two at that point. The common legacy pattern of
     * wrapping an entire stylesheet in a comment (`<style><!-- ...css...
     * --></style>`) is unaffected, since all of its CSS ends up inside the
     * resulting comment segment and is never rendered; only a partial one
     * leaks its tail, and what leaks is inert text rather than a live tag.
     * A proper fix needs a comment-aware linear scanner rather than regexes.
     *
     * @since 1.17.0
     *
     * @return void
     */
    private function stripStyleBlocks() {

        // Split on HTML comments, keeping them. Comment contents are left
        // verbatim: they are not rendered, and a style regex reaching across a
        // comment boundary would consume the terminator and leave a dangling
        // marker. With one capture group, odd indices are the comments.
        $parts = preg_split( '#(<!--.*?-->)#s', $this->buffer, -1, PREG_SPLIT_DELIM_CAPTURE );

        foreach ( $parts as $i => $part ) {

            if ( $i % 2 === 1 ) {
                continue;
            }

            // Well-formed blocks first, then an unterminated one to the end of
            // the segment, which is how a browser parses it.
            $part = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $part );
            $parts[ $i ] = preg_replace( '#<style\b[^>]*>.*$#is', '', $part );
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

        unset( $allowed_tags['a']['target'], $allowed_tags['a']['rel'] );

        $this->buffer = wp_kses( $this->buffer, $allowed_tags );
    }

}
