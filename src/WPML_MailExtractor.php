<?php

namespace No3x\WPML;


use No3x\WPML\Model\WPML_Mail as Mail;

class WPML_MailExtractor {

    const ERROR_NO_FIELD = "The message is not valid because it contains no message or html field.";

    public function __construct() {
    }

    public function extract($mailArray) {
        return Mail::create([
            'receiver' => $this->extractReceiver($mailArray['to']),
            'subject' => $mailArray['subject'],
            'message' => $this->extractMessage($mailArray),
            'headers' => $this->extractHeader($mailArray),
            'attachments' => $this->extractAttachments($mailArray),
        ]);
    }

    private function extractReceiver( $receiver ) {
        return $this->convertMultipartsToString($receiver);
    }

    private function extractMessage( $mail ) {
        if ( isset($mail['message']) ) {
            // usually the message is stored in the message field
            return $mail['message'];
        } elseif ( isset($mail['html']) ) {
            // for example Mandrill stores the message in the 'html' field (see gh-22)
            return $mail['html'];
        }
        throw new \Exception(self::ERROR_NO_FIELD);
    }

    private function extractHeader( $mail ) {
        $headers = isset($mail['headers']) ? $mail['headers'] : array();
        return $this->joinMultiParts($headers);
    }

    private function extractAttachments( $mail ) {
        $attachmentAbsPaths = isset($mail['attachments']) ? $mail['attachments'] : array();

        if(!is_array($attachmentAbsPaths)) {
            $attachmentAbsPaths = $this->splitAtComma($attachmentAbsPaths);
        }

        $attachment_urls = [];
        foreach ($attachmentAbsPaths as $attachmentAbsPath) {
            $attachment = WPML_Attachment::fromAbsPath($attachmentAbsPath);
            $attachment_urls[] = $attachment->toRelPath();
        }

        $string = $this->joinArrayWithCommaAndNewLine($attachment_urls);

        return $string;
    }

    private function convertMultipartsToString($multiparts) {

        if(is_array($multiparts)) {
            $multiPartArray = $multiparts;
        } else {
            $multiPartArray = $this->splitAtComma($multiparts);
        }

        $string = $this->joinArrayWithCommaAndNewLine($multiPartArray);

        return $string;
    }

    private function splitAtComma($string) {
        $parts = preg_split( "/(,|,\s)/", $string );
        return $parts;
    }

    private function joinMultiParts($multiPart) {
        return is_array($multiPart) ? $this->joinArrayWithCommaAndNewLine($multiPart) : $multiPart;
    }

    private function joinArrayWithCommaAndNewLine(array $array) {
        return implode(',\n', $array);
    }
}
