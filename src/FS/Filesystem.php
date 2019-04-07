<?php

namespace No3x\WPML\FS;

class Filesystem implements IFilesystem
{

    /**
     * @inheritdoc
     */
    function is_file($filename) {
        return is_file($filename);
    }

    /**
     * @inheritdoc
     */
    function mime_content_type($filename) {
        return mime_content_type($filename);
    }
}
