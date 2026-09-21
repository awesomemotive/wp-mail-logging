<?php

namespace No3x\WPML\Tests;

use Mockery;
use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\Renderer\WPML_MailRenderer;
use No3x\WPML\Tests\Helper\WPMailArrayBuilder;
use No3x\WPML\WPML_MailExtractor;

class WPML_MailRenderer_Test extends \PHPUnit_Framework_TestCase {

    /** @var WPML_MailRenderer */
    private $mailRenderer;

    /** @var $mailServiceMock \No3x\WPML\Model\IMailService|\Mockery\MockInterface */
    private $mailServiceMock;

    private $id = 2;

    public function setUp() {
        $this->mailServiceMock = Mockery::mock('No3x\WPML\Model\IMailService');

        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()
            ->withSubject("Test")
            ->withTo("example@exmple.com")
            ->withHeaders("From: \"admin\" <admin@local.test>\r\n,\nCc: example2@example.com,\nReply-To: admin <admin@local.test>\r\n")
            ->withMessage("<b>Bold</b><script>alert('xss');</script>")
            ->withAttachments(["file.pdf"])
            ->build());
        $mail->set_mail_id($this->id);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');
        $mail->set_error('a');

        $this->mailServiceMock->shouldReceive('find_one')
            ->times(1)
            ->with( $this->id )
            ->andReturn( $mail );

        $this->mailRenderer = new WPML_MailRenderer($this->mailServiceMock);
    }

    public function test_print_mail_json() {
        $expected = '<pre>{
    "mail_id": "2",
    "timestamp": "2018-09-24 16:02:11",
    "host": "127.0.0.1",
    "receiver": "example@exmple.com",
    "subject": "Test",
    "message": "&lt;b&gt;Bold&lt;\/b&gt;&lt;script&gt;alert(\'xss\');&lt;\/script&gt;",
    "headers": "From: &quot;admin&quot; &lt;admin@local.test&gt;\r\n,\nCc: example2@example.com,\nReply-To: admin &lt;admin@local.test&gt;\r\n",
    "attachments": "file.pdf",
    "error": "a",
    "plugin_version": "1.8.5"
}</pre>';

        $this->assertEquals($expected, $this->mailRenderer->render($this->id, WPML_MailRenderer::FORMAT_JSON));
        $this->mailServiceMock->mockery_verify();
    }

    /**
     * Regression guard for #227: rendering a mail whose message is null must not raise a
     * PHP 8.1+ "Passing null to parameter" deprecation.
     *
     * Without the (string) cast in JSONRenderer::renderModal(), the null message reaches
     * htmlspecialchars_decode() as null, which PHP 8.1+ reports as an E_DEPRECATED notice.
     * The preview shows an empty string either way, so asserting only the output would pass
     * with or without the fix; this captures deprecations so removing the cast fails it.
     *
     * @since {VERSION}
     */
    public function test_print_mail_json_null_message_raises_no_deprecation() {

        $this->mailServiceMock = Mockery::mock('No3x\WPML\Model\IMailService');

        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()
            ->withSubject("Test")
            ->withTo("example@exmple.com")
            ->withHeaders("Content-Type: text/plain")
            ->withMessage("Placeholder")
            ->build());
        $mail->set_mail_id($this->id);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');
        $mail->set_error('a');
        // The extractor rejects a null message, so null it afterwards to model a NULL
        // `message` column - the schema declares it TEXT NULL.
        $mail->set_message(null);

        $this->mailServiceMock->shouldReceive('find_one')
            ->times(1)
            ->with( $this->id )
            ->andReturn( $mail );

        $this->mailRenderer = new WPML_MailRenderer($this->mailServiceMock);

        $deprecations = [];
        set_error_handler(
            function ($errno, $errstr) use (&$deprecations) {
                $deprecations[] = $errstr;
                return true;
            },
            E_DEPRECATED
        );

        try {
            $actual = $this->mailRenderer->render($this->id, WPML_MailRenderer::FORMAT_JSON);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(
            [],
            $deprecations,
            'Rendering a null message as JSON must not trigger a PHP deprecation; got: ' . implode(' | ', $deprecations)
        );
        $this->assertContains(
            '&quot;message&quot;: &quot;&quot;',
            $actual,
            'A null message must still render as an empty string in the JSON preview'
        );
        $this->mailServiceMock->mockery_verify();
    }

	/**
	 * Verify header formatting in both popup views.
	 *
	 * @access public
	 * @dataProvider popup_headers_provider
	 * @param string|null $headers Stored headers.
	 * @param string|null $expected Expected visible header text, or no row.
	 * @return void
	 */
	public function test_popup_headers_display( $headers, $expected ) {

		$previous_post = $_POST;

		try {
			foreach ( [ 'html', 'raw' ] as $format ) {
				$_POST['format'] = $format;
				$renderer = \No3x\WPML\Renderer\Format\MailRendererFactory::factory( $format );
				$html = $renderer->renderModal( [ 'headers' => $headers, 'message' => '' ] );
				$document = new \DOMDocument();
				$document->loadHTML( $html );
				$xpath = new \DOMXPath( $document );
				$values = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " wp-mail-logging-modal-row-value-headers ")]' );

				if ( $expected === null ) {
					$this->assertSame( 0, $values->length, $format );
					continue;
				}

				$this->assertSame( 1, $values->length, $format );
				$value = $values->item( 0 );
				$this->assertSame( $expected, trim( $value->textContent ), $format );
				$this->assertSame( substr_count( $expected, "\n" ), $value->getElementsByTagName( 'br' )->length, $format );
				$this->assertSame( 0, $xpath->query( './/*[not(self::br)]', $value )->length, $format );
				$this->assertSame( 0, $xpath->query( '//script | //img' )->length, $format );
			}
		} finally {
			$_POST = $previous_post;
		}
	}

	/**
	 * Supply stored header formats and expected popup text.
	 *
	 * @access public
	 * @return array
	 */
	public function popup_headers_provider() {

		$first = 'Content-Type: text/html';
		$second = 'Reply-to: theevoeon.com <donmhico@gmail.com>';
		$expected = $first . "\n" . $second;
		$long = 'X-Long: ' . str_repeat( 'a', 400 );

		return [
			'legacy LF' => [ $first . ',\n' . $second, $expected ],
			'legacy CRLF' => [ $first . ',\r\n' . $second, $expected ],
			'literal LF' => [ $first . '\n' . $second, $expected ],
			'literal CRLF' => [ $first . '\r\n' . $second, $expected ],
			'actual LF' => [ $expected, $expected ],
			'actual CRLF' => [ $first . "\r\n" . $second, $expected ],
			'single header' => [ $first, $first ],
			'empty' => [ '', null ],
			'null' => [ null, null ],
			'long header' => [ $long, $long ],
			'values and escapes' => [
				'Cc: "Doe, Jane" <jane@example.com>, bob@example.com,\nX-Notes: Q&A C:\\Temp\\Report',
				"Cc: \"Doe, Jane\" <jane@example.com>, bob@example.com\nX-Notes: Q&A C:\\Temp\\Report",
			],
			'continuation and value comma' => [
				"Cc: jane@example.com,\r\n bob@example.com",
				"Cc: jane@example.com,\n bob@example.com",
			],
			'header markup' => [
				'X-Test: <script>alert(1)</script>,\nX-Image: <img src=x onerror=alert(1)>',
				"X-Test: <script>alert(1)</script>\nX-Image: <img src=x onerror=alert(1)>",
			],
		];
	}

    public function test_supported_formats() {
        $this->assertEquals(['raw', 'html', 'json'], $this->mailRenderer->getSupportedFormats());
    }

    public function test_print_mail_json_fallback_to_raw() {
        $this->mailServiceMock = Mockery::mock('No3x\WPML\Model\IMailService');

        /** @var $mail WPML_Mail */
        $mail = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()
            ->withSubject("Test")
            ->withTo("example@exmple.com")
            ->withHeaders("Content-Type: text/html")
            ->withMessage("Message")
            ->build());
        $mail->set_mail_id($this->id);
        $mail->set_plugin_version('1.8.5');
        $mail->set_timestamp('2018-09-24 16:02:11');
        $mail->set_host('127.0.0.1');
        $mail->set_error('a');

        $this->mailServiceMock->shouldReceive('find_one')
            ->times(1)
            ->with( $this->id )
            ->andReturn( $mail );

        $this->mailRenderer = new WPML_MailRenderer($this->mailServiceMock);
        $this->assertContains("Fallback", $this->mailRenderer->render($this->id, WPML_MailRenderer::FORMAT_JSON));
        $this->mailServiceMock->mockery_verify();
    }

    public function test_print_mail_raw() {
        $expected = '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span>&lt;b&gt;Bold&lt;/b&gt;<span class="title">Headers: </span>From: &quot;admin&quot; ,\nCc: example2@example.com,\nReply-To: admin <span class="title">Attachments: </span><span class="title">Error: </span><i class="fa fa-exclamation-circle" title="a"></i>';
        $actual = $this->mailRenderer->render($this->id, WPML_MailRenderer::FORMAT_RAW);
        $this->assertContains('2018-09-24 16:02:11', $actual, "The timestamp should be in the rendered mail");
        $this->assertContains('Test', $actual, "The subject should be in the rendered mail");
        $this->assertContains('&lt;b&gt;Bold&lt;/b&gt;', $actual, "The rendered mail must have html tags (<b>) escaped");
        $this->assertNotContains('<script>alert(', $actual, "The rendered mail must strip out evil tags to protect against xss");
        $this->assertNotContains('<i', $actual, "The rendered mail has no icons set because it show the  and attachments raw");
    }

    public function test_print_mail_html() {
        $expected = '<span class="title">Time: </span>2018-09-24 16:02:11<span class="title">Receiver: </span>example@exmple.com<span class="title">Subject: </span>Test<span class="title">Message: </span><b>Bold</b><span class="title">Headers: </span>From: "admin" ,\nCc: example2@example.com,\nReply-To: admin <span class="title">Attachments: </span><span class="title">Error: </span><i class="fa fa-exclamation-circle" title="a"></i>';
        $actual = $this->mailRenderer->render($this->id, WPML_MailRenderer::FORMAT_HTML);
        $this->assertContains('2018-09-24 16:02:11', $actual, "The timestamp should be in the rendered mail");
        $this->assertContains('Test', $actual, "The subject should be in the rendered mail");
        $this->assertContains('<b>Bold</b>', $actual, "The rendered mail must have html tags (<b>) not escaped");
        $this->assertNotContains('<script>alert(', $actual, "The rendered mail must strip out evil tags to protect against xss");
        $this->assertContains('<i class="fa fa-exclamation-circle"', $actual, "The rendered mail has icons for the error returned as html, it must not be escaped");
        $this->assertContains('<i class="fa fa-times"', $actual, "The rendered mail has icons for the attachments returned as html, it must not be escaped");
    }

    /**
     * @dataProvider evilTextProvider
     * @param $evilText string the message to be rendered
     * @param $expected string the expected output
     */
    function test_messageSanitationOnMessageAndSubject($evilText, $expected) {

        $this->mailServiceMock = Mockery::mock('No3x\WPML\Model\IMailService');

        /** @var $mail_raw WPML_Mail */
        $mail_raw = (new WPML_MailExtractor())->extract(WPMailArrayBuilder::aMail()
            ->withSubject($evilText)
            ->withTo("example@exmple.com")
            ->withHeaders("From: \"admin\" <admin@local.test>\r\n,\nCc: example2@example.com,\nReply-To: admin <admin@local.test>\r\n")
            ->withMessage($evilText)
            ->build())
        ;
        $mail_raw->set_mail_id($this->id);
        $mail_raw->set_plugin_version('1.8.5');
        $mail_raw->set_timestamp('2018-09-24 16:02:11');
        $mail_raw->set_host('127.0.0.1');
        $mail_raw->set_error('a');

        /** @var $mail_html WPML_Mail */
        $mail_html = WPML_Mail::create($mail_raw->to_array());
        $mail_html->set_subject($evilText);
        $mail_html->set_message($evilText);

        $this->mailServiceMock->shouldReceive('find_one')
            ->times(1)
            ->with( $this->id )
            ->andReturn( $mail_raw )
            // And then return $mail_html
            ->andReturn( $mail_html )
        ;

        $this->mailRenderer = new WPML_MailRenderer($this->mailServiceMock);

        $this->assertMessageAndTitleEqual($expected[0], WPML_MailRenderer::FORMAT_RAW);
        $this->assertMessageAndTitleEqual($expected[1], WPML_MailRenderer::FORMAT_HTML);
    }

    private function assertMessageAndTitleEqual($expected, $format) {
        $mail = $this->mailRenderer->render($this->id, $format);
        $mail_message = $this->get_string_between($mail, 'Message: </span>', '<span ');
        $mail_subject = $this->get_string_between($mail, 'Subject: </span>', '<span ');
        $this->assertEquals($expected, $mail_message);
        $this->assertEquals($expected, $mail_subject);
    }

    private function get_string_between($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return "Could not find '{$start}' in the string";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    function evilTextProvider() {
        return [
            "plaintext" => [
                "Hello World",
                [
                    "Hello World",
                    "Hello World",
                ]
            ],
            "html bold" => [
                "<b>Hello World</b>",
                [
                    "&lt;b&gt;Hello World&lt;/b&gt;",
                    "<b>Hello World</b>",
                ]
            ],
            "style" => [
                "<style>body {background-color: red;}</style>",
                [
                    "&lt;style&gt;body {background-color: red;}&lt;/style&gt;",
                    "<style>body {background-color: red;}</style>",
                ]
            ],
            "script alert()" => [
                "<script>alert('XSS hacking!');</script>",
                [
                    "&lt;script&gt;alert('XSS hacking!');&lt;/script&gt;",
                    "alert('XSS hacking!');",
                ]
            ],
            "html comment" => [
                "<!-- Comment -->",
                [
                    "&lt;!-- Comment --&gt;",
                    "<!-- Comment -->",
                ]
            ],
            "html encoded comment" => [
                "&lt;!-- Comment --&gt;",
                [
                    "&lt;!-- Comment --&gt;",
                    "&lt;!-- Comment --&gt;",
                ]
            ],
            "html embedded tag in comment" => [
                "<!-- <b>This is commented out actually</b> -->",
                [
                    "&lt;!-- &lt;b&gt;This is commented out actually&lt;/b&gt; --&gt;",
                    "<!-- <b>This is commented out actually</b> -->",
                ]
            ],
        ];
    }

}
