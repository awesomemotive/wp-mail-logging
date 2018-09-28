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

        $this->saveComments();
        $this->stripEvilCode();
        $this->recoverComments();

        return $this->buffer;
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
        $allowed_tags['style'][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLEntity_OPEN][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLEntity_CLOSE][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLCode_OPEN][''] = true;
        $allowed_tags[self::SAVED_COMMENT_HTMLCode_CLOSE][''] = true;

        $this->buffer = wp_kses( $this->buffer, $allowed_tags );
    }

}
