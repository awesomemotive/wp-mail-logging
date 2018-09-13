<?php

namespace No3x\WPML\Tests\Integration;


use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_Plugin;

class WPML_Plugin_Test extends WPML_IntegrationTestCase {

    function test_log_email() {
        global $wpdb;
        $tableName = WPML_Plugin::getTablename( 'mails' );

        $to = array(
            'email@example.com',
            'email2@example.com'
        );

        $subject = rand_str();
        $message = "Hello, this is a test message";

        wp_mail($to, $subject, $message);

        $rows = $wpdb->get_results( "SELECT * FROM $tableName WHERE subject = '{$subject}'" );

        $count = count( $rows );
        $this->assertEquals( 1, $count);

        $row = $rows[0];
        $this->assertEquals( $subject,  $row->subject );
        $this->assertEquals( $message,  $row->message );

        $this->assertTrue( strpos( $row->receiver, $to[0] ) !== false );
        $this->assertTrue( strpos( $row->receiver, $to[1] ) !== false );
    }

    function test_charset_email() {
        global $wpdb;
        $tableName = WPML_Plugin::getTablename( 'mails' );

        $to = array(
            'email@example.com',
            'email2@example.com'
        );

        $subject = rand_str();
        $message = "Řekl, že přijde, jestliže v konkurzu zvítězí";

        wp_mail($to, $subject, $message);

        $rows = $wpdb->get_results( "SELECT * FROM $tableName" );
        $row = $rows[0];

        $this->assertTrue( strpos( $row->message, "?" ) === false );

    }
}
