<?php
/**
 * Created by IntelliJ IDEA.
 * User: czoeller
 * Date: 08.06.17
 * Time: 15:48
 */

namespace No3x\WPML;


class WPML_Email_Dispatcher {

    public function dispatch( $to, $subject, $message, $headers = '', $attachments = array() )
    {
        wp_mail( $to, $subject, $message, $headers, $attachments);
    }
}
