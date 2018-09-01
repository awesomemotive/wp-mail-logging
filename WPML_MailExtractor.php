<?php

namespace No3x\WPML;


use No3x\WPML\Model\WPML_Mail as Mail;

class WPML_MailExtractor {
    /**
     * WPML_MailExtractor constructor.
     */
    public function __construct() {
    }

    public function extract($mailArray) {
        return Mail::create([
            'receiver' => $this->extractReceiver($mailArray['to']),
            'subject' => $mailArray['subject'],
            'message' => $this->extractMessage($mailArray),
            'headers' => $this->extractHeader($mailArray['headers']),
            'attachments' => $this->extractAttachments($mailArray),
        ]);
    }

    private function extractReceiver( $receiver ) {
        return $this->convertAddressesToString($receiver);
    }

    private function extractMessage( $mail ) {
        if ( isset($mail['message']) ) {
            // usually the message is stored in the message field
            return $mail['message'];
        } elseif ( isset($mail['html']) ) {
            // for example Mandrill stores the message in the 'html' field (see gh-22)
            return $mail['html'];
        }
        return "";
    }

    private function extractHeader( $headers ) {
        return is_array( $headers ) ? implode( ',\n', $headers ) : $headers;
    }

    private function extractAttachments( $mail ) {
        $attachments = isset($mail['attachments']) ? $mail['attachments'] : array();
        $attachments = is_array( $attachments ) ? $attachments : array( $attachments );
        $attachment_urls = array();
        $basename = 'uploads';
        $basename_needle = '/'.$basename.'/';
        foreach ( $attachments as $attachment ) {
            $append_url = substr( $attachment, strrpos( $attachment, $basename_needle ) + strlen($basename_needle) - 1 );
            $attachment_urls[] = $append_url;
        }
        return implode( ',\n', $attachment_urls );
    }

    private function convertAddressesToString($addresses) {

        if(is_array($addresses)) {
            $addressesArray = $addresses;
        } else {
            $addressesArray = $this->splitAddressesSeparatedBy($addresses);
        }

        $string = $this->joinAddressesWithCommaAndNewLine($addressesArray);

        return $string;
    }

    private function joinAddressesWithCommaAndNewLine(array $addresses) {
        return implode(',\n', $addresses);
    }

    private function splitAddressesSeparatedBy($addresses) {
        $addressesArr = preg_split( "/(,|,\s)/", $addresses );
        return $addressesArr;
    }
}
