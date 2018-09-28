<?php

namespace No3x\WPML\Tests;

use Mockery;
use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\Tests\Helper\WPMailArrayBuilder;
use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_FormattedPrinter;
use No3x\WPML\WPML_MailExtractor;

// TODO: after refactoring this should be a unittestcase
class WPML_FormattedPrinter_Test extends WPML_IntegrationTestCase {

    /** @var WPML_FormattedPrinter */
    private $formattedPrinter;

    /** @var $mailServiceMock \No3x\WPML\Model\IMailService|\Mockery\MockInterface */
    private $mailServiceMock;


    private $id = 2;

    public function setUp() {
        $this->mailServiceMock = Mockery::mock('No3x\WPML\Model\IMailService');

        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()->withSubject("Test")->withTo("example@exmple.com")->withHeaders("From: \"admin\" <admin@local.test>\r\n,\nCc: example2@example.com,\nReply-To: admin <admin@local.test>\r\n")->withMessage("<b>Bold</b><script>alert('xss');</script>")->build());
        $mail->set_mail_id($this->id);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');
        $mail->set_error('a');

        $this->mailServiceMock->shouldReceive('find_one')
            ->times(1)
            ->with( $this->id )
            ->andReturn( $mail );

        $this->formattedPrinter = new WPML_FormattedPrinter($this->mailServiceMock, ['json', 'html']);
    }

    /**
     * @dataProvider messagesProvider
     * @param $format string
     * @param $expected string the expected output
     */
    public function test_json($format, $expected) {
        $this->assertEquals($expected, $this->formattedPrinter->print_email($this->id, $format));
        $this->mailServiceMock->mockery_verify();
    }

    function messagesProvider() {
        $json = '<pre>{
    "mail_id": 2,
    "timestamp": "2018-09-24 16:02:11",
    "host": "127.0.0.1",
    "receiver": "example@exmple.com",
    "subject": "Test",
    "message": "<b>Bold<\/b>",
    "headers": "From: \"admin\" <admin@local.test>\\r\\n,\\nCc: example2@example.com,\\nReply-To: admin <admin@local.test>\\r\\n",
    "attachments": "",
    "error": null,
    "plugin_version": "1.8.5"
}</pre>';

        return [
            "json" => [
                "json", 
                $json           
            ],
            "raw" => [
                "raw",
                '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span>&lt;b&gt;Bold&lt;/b&gt;<span class="title">Headers: </span>From: &quot;admin&quot; \\r\\n,\\nCc: example2@example.com,\\r\\nReply-To: admin \\r\\n<span class="title">Attachments: </span><span class="title">Error: </span>'
            ],
            "html" => [
                "html",
                '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>The subject<span class="title">Message: </span>This is a message<span class="title">Headers: </span><span class="title">Attachments: </span><span class="title">Error: </span>'
            ],
        ];
    }

    public function test_render_mail() {
        $expected = '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span>&lt;b&gt;Bold&lt;/b&gt;<span class="title">Headers: </span>From: &quot;admin&quot; ,\nCc: example2@example.com,\nReply-To: admin <span class="title">Attachments: </span><span class="title">Error: </span><i class="fa fa-exclamation-circle" title="a"></i>';
        $actual = $this->formattedPrinter->print_email($this->id, 'raw');
        $this->assertContains('2018-09-24 16:02:11', $actual, "The timestamp should be in the rendered mail");
        $this->assertContains('Test', $actual, "The subject should be in the rendered mail");
        $this->assertContains('&lt;b&gt;Bold&lt;/b&gt;', $actual, "The rendered mail must have html tags (<b>) escaped");
        $this->assertNotContains('<script>alert(', $actual, "The rendered mail must strip out evil tags to protect against xss");
        $this->assertContains('<i class="fa fa-exclamation-circle"', $actual, "The rendered mail has icons for the attachments returned as html, it must not be escaped");

    }

    public function test_render_mail_html() {
        $expected = '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span><b>Bold</b><span class="title">Headers: </span>From: "admin" ,\nCc: example2@example.com,\nReply-To: admin <span class="title">Attachments: </span><span class="title">Error: </span><i class="fa fa-exclamation-circle" title="a"></i>';
        $actual = $this->formattedPrinter->print_email($this->id, 'html');
        $this->assertContains('2018-09-24 16:02:11', $actual, "The timestamp should be in the rendered mail");
        $this->assertContains('Test', $actual, "The subject should be in the rendered mail");
        $this->assertContains('<b>Bold</b>', $actual, "The rendered mail must have html tags (<b>) not escaped");
        $this->assertNotContains('<script>alert(', $actual, "The rendered mail must strip out evil tags to protect against xss");
        $this->assertContains('<i class="fa fa-exclamation-circle"', $actual, "The rendered mail has icons for the attachments returned as html, it must not be escaped");
    }
}
