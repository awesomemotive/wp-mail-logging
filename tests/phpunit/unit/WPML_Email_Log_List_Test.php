<?php
namespace No3x\WPML\Tests;


use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\Tests\Helper\WPMailArrayBuilder;
use No3x\WPML\WPML_Email_Log_List;
use No3x\WPML\WPML_MailExtractor;

class WPML_Email_Log_List_Test extends \PHPUnit_Framework_TestCase {

    private $logListTable;

    private $item;
    private $item_id;

    public function setUp() {
        $this->logListTable = new WPML_Email_Log_List(null);

        $this->item_id = 2;

        $wpMailArrayBuilder = WPMailArrayBuilder::aMail()
            ->withSubject("Test")
            ->withTo("example@exmple.com")
            ->withMessage("Hello World")
        ;
        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract($wpMailArrayBuilder->build());
        $mail->set_mail_id($this->item_id);

        $this->item = $mail->to_array();
    }

    public function test_column_message() {
        $expected = '<a class="wp-mail-logging-view-message button button-secondary" href="#" data-mail-id="' . $this->item_id . '">View</a>';
        $this->assertEquals($expected, $this->logListTable->column_message($this->item));
    }
}
