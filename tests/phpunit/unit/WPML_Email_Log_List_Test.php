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
        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()->withSubject("Test")->withTo("example@exmple.com")->withHeaders(["From: \"admin\" <admin@local.test>", "Cc: example2@example.com", "Reply-To: admin <admin@local.test>"])->withMessage("<b>Bold</b>")->build());
        $mail->set_mail_id($this->item_id);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');
        $mail->set_error('a');

        $this->item = $mail->to_array();
    }

    public function test_column_message() {
        $expected = '<a class="wp-mail-logging-view-message button button-secondary" href="#" data-mail-id="' . $this->item_id . '">View</a>';
        $this->assertEquals($expected, $this->logListTable->column_message($this->item));
    }

    public function test_column_timestamp() {
        $expected = '2018-09-24 16:02:11';
        $this->assertEquals($expected, $this->logListTable->column_default($this->item, "timestamp"));
    }

    public function test_column_error_empty() {
        $expected = '';
        $this->item['error'] = "";
        $this->assertEquals($expected, $this->logListTable->column_default($this->item, "error"));
    }

    public function test_column_error_array() {
        $expected = '<i class="fa fa-exclamation-circle" title="a"></i>';
        $actual = $this->logListTable->column_default($this->item, "error");
        $this->assertEquals($expected, $actual);
    }

    public function test_render_mail() {
        $expected = '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span>&lt;b&gt;Bold&lt;/b&gt;<span class="title">Headers: </span>From: &quot;admin&quot; ,\nCc: example2@example.com,\nReply-To: admin <span class="title">Attachments: </span><span class="title">Error: </span><i class="fa fa-exclamation-circle" title="a"></i>';
        $this->assertEquals($expected, $this->logListTable->render_mail($this->item));
    }

   public function test_render_mail_html() {
        $expected = '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span><b>Bold</b><span class="title">Headers: </span>From: "admin" ,\nCc: example2@example.com,\nReply-To: admin <span class="title">Attachments: </span><span class="title">Error: </span><i class="fa fa-exclamation-circle" title="a"></i>';
        $this->assertEquals($expected, $this->logListTable->render_mail_html($this->item));
    }

}
