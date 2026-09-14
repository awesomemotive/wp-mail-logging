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
     * element is removed entirely rather than allowed.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function stripStyleBlocks() {

        $this->buffer = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $this->buffer );
        $this->buffer = preg_replace( '#<style\b[^>]*/?>#i', '', $this->buffer );
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
