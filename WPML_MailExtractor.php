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
        return Mail::create(['receiver' => $this->extractReceiver($mailArray['to'])]);
    }

    private function extractReceiver( $receiver ) {
        return $this->convertAddressesToString($receiver);
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
