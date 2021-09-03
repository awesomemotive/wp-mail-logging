<?php

namespace No3x\WPML\Tests;


use No3x\WPML\WPML_Attachment;

class WPML_Attachment_Test extends \PHPUnit_Framework_TestCase {

    /** @var $fsMock \No3x\WPML\FS\IFilesystem |\Mockery\MockInterface */
    private $fsMock;

    function setUp()
    {
        parent::setUp();

        $this->fsMock = self::getMockBuilder('No3x\WPML\FS\IFilesystem')
                ->disableOriginalConstructor()
                ->getMock()
        ;

        WPML_Attachment::setFS($this->fsMock);
    }


    public function test_fromRelativePath() {

        $this->fsMock->expects(self::once())
            ->method('is_file')
            ->willReturn(true)
        ;

        // The mimetype should not be determined, if it's not a file
        $this->fsMock->expects(self::once())
            ->method('mime_content_type')
            ->willReturn('image/png')
        ;

        $path = "/uploads/2000/04/image.png";

        $attachment = WPML_Attachment::fromRelPath($path);
        $this->assertFalse($attachment->isGone());
        $this->assertEquals("image", $attachment->getIconClass());
        $this->assertEquals(WP_CONTENT_DIR . '/uploads' . $path, $attachment->getPath());
    }

    public function test_fromRelativePath_InvalidPath() {

        $this->fsMock->expects(self::once())
            ->method('is_file')
            ->willReturn(false)
        ;

        // The mimetype should not be determined, if it's not a file
        $this->fsMock->expects(self::never())
            ->method('mime_content_type')
        ;

        $path = "/invalid";

        $attachment = WPML_Attachment::fromRelPath($path);
        $this->assertTrue($attachment->isGone());
        $this->assertEquals("file", $attachment->getIconClass());
        $this->assertEquals("/invalid", $attachment->getPath());
    }

    function test_fromAbsolutePath() {

        $this->fsMock->expects(self::never())
            ->method('is_file')
        ;

        // The mimetype should not be determined, if it's not a file
        $this->fsMock->expects(self::once())
            ->method('mime_content_type')
            ->willReturn('image/png')
        ;

        $path = WP_CONTENT_DIR . '/uploads/2000/04/image.png';

        $attachment = WPML_Attachment::fromAbsPath($path);
        $this->assertFalse($attachment->isGone());
        $this->assertEquals("image", $attachment->getIconClass());
        $this->assertEquals($path, $attachment->getPath());

    }

    function test_fromAbsolutePath_InvalidPath() {

        $this->fsMock->expects(self::never())
            ->method('is_file')
        ;

        // The mimetype should not be determined, if it's not a file
        $this->fsMock->expects(self::once())
            ->method('mime_content_type')
            ->willReturn(false)
        ;

        $path = WP_CONTENT_DIR . '/a/file/somewhere/else.png';
        $path = WP_CONTENT_DIR . '../../else.png';

        $attachment = WPML_Attachment::fromAbsPath($path);
        $this->assertEquals("file", $attachment->getIconClass());
        $this->assertEquals($path, $attachment->getPath());
        $this->assertEquals("else.png", $attachment->toRelPath());

    }

}
