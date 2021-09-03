<?php

namespace No3x\WPML\Tests;


use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\Exception\ColumnDoesntExistException;
use No3x\WPML\Renderer\WPML_ColumnManager;
use No3x\WPML\Tests\Helper\WPMailArrayBuilder;
use No3x\WPML\WPML_MailExtractor;

class WPML_ColumnManager_Test extends \PHPUnit_Framework_TestCase {

    private $columnManager;
    /** @var WPML_Mail */
    private $mail;
    private $item;

    public function setUp() {
        $this->columnManager = new WPML_ColumnManager();

        $exampleAttachment1 = WP_CONTENT_DIR . '/uploads/2018/05/file.pdf';
        $exampleAttachment2 = WP_CONTENT_DIR . '/uploads/2018/01/bill.pdf';

        $mailArrayBuilder = WPMailArrayBuilder::aMail()
            ->withSubject("Test")
            ->withTo("example@exmple.com")
            ->withMessage("<b>Bold</b>")
            ->withHeaders("From: \"admin\" <admin@local.test>\r\n,\nCc: example2@example.com,\nReply-To: admin <admin@local.test>\r\n")
            ->withAttachments([$exampleAttachment1, $exampleAttachment2])
        ;

        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract($mailArrayBuilder->build());
        $mail->set_mail_id(2);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');
        $mail->set_error('bli');

        $this->item = $mail->to_array();
    }

    public function test_columns() {
        $columns = ['mail_id', 'timestamp', 'host', 'receiver', 'subject', 'message', 'headers', 'attachments', 'error', 'plugin_version'];
        $this->assertEquals($columns, $this->columnManager->getColumnNames());
    }

    /**
     * @requires PHPUnit 5.2
     */
    public function test_nonexistentdata() {
        $column_name = 'host';
        unset($this->item[$column_name]);
        $this->expectException(ColumnDoesntExistException::get_class());
        $this->expectExceptionMessage(sprintf(ColumnDoesntExistException::MESSAGE, $column_name));
        $this->columnManager->getColumnRenderer($column_name)->render($this->item, ColumnFormat::FULL);
    }

    public function test_column_host() {
        $actual = $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_HOST)->render($this->item, ColumnFormat::FULL);
        $this->assertEquals('127.0.0.1', $actual);
    }

    //TODO: need to test both time formats
    public function test_column_timestamp() {
        $actual = $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_TIMESTAMP)->render($this->item, ColumnFormat::FULL);
        $this->assertEquals('2018-09-24 16:02:11', $actual);
    }

    public function test_column_attachments_simple() {
        $example1And2Expected = '/2018/05/file.pdf,\n/2018/01/bill.pdf';
        $actual = $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_ATTACHMENTS)->render($this->item, ColumnFormat::SIMPLE);
        $this->assertEquals($example1And2Expected, $actual);
    }

    public function test_column_attachments_full() {
        $example1And2Expected = '<i class="fa fa-times" title="Attachment file.pdf is not present"></i><i class="fa fa-times" title="Attachment bill.pdf is not present"></i>';
        $actual = $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_ATTACHMENTS)->render($this->item, ColumnFormat::FULL);
        $this->assertEquals($example1And2Expected, $actual);
    }

    public function test_column_error_simple() {
        $example1And2Expected = "bli";
        $actual = $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_ERROR)->render($this->item, ColumnFormat::SIMPLE);
        $this->assertEquals($example1And2Expected, $actual);
    }

    public function test_column_error_empty() {
        $expected = '';
        $this->item['error'] = "";
        $this->assertEquals($expected, $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_ERROR)->render($this->item, ColumnFormat::FULL));
    }

    public function test_column_error_full() {
        $example1And2Expected = '<i class="fa fa-exclamation-circle" title="bli"></i>';
        $actual = $this->columnManager->getColumnRenderer(WPML_ColumnManager::COLUMN_ERROR)->render($this->item, ColumnFormat::FULL);
        $this->assertEquals($example1And2Expected, $actual);
    }
}
