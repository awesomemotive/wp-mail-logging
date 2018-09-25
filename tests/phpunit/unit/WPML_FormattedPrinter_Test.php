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

    /** @var $mailServiceMock \No3x\WPML\Model\IMailService|\Mockery\MockInterface */
    private $mailServiceMock;

    /**
     * Swap ChromeProcess with ChromeProcessMock
     * so Chrome isn't really launched for each test.
     */
    public function setUp() {
        $this->mailServiceMock = Mockery::mock('No3x\WPML\Model\IMailService');
    }

    /**
     * @dataProvider messagesProvider
     * @param $format string
     * @param $expected string the expected output
     */
    public function test_json($format, $expected) {
        $printer = new WPML_FormattedPrinter( $this->mailServiceMock, ['json', 'html']);
        $id = 2;

        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()->withSubject("Test")->withTo("example@exmple.com")->withHeaders("From: \"admin\" <admin@local.test>\r\n,\nCc: example2@example.com,\nReply-To: admin <admin@local.test>\r\n")->withMessage("<b>Bold</b>")->build());
        $mail->set_mail_id($id);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');

        $this->mailServiceMock->shouldReceive('find_one')
            ->times(1)
            ->with( $id )
            ->andReturn( $mail );

        $this->assertEquals($expected, $printer->print_email($id, $format));

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
}
