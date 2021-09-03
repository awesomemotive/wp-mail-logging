<?php

namespace No3x\WPML\FS;

interface IFilesystem
{
    /**
    * Tells whether the filename is a regular file
    * @link https://php.net/manual/en/function.is-file.php
    * @param string $filename <p>
    * Path to the file.
    * </p>
    * @return bool true if the filename exists and is a regular file, false
    */
    function is_file ($filename);

    /**
     * Detect MIME Content-type for a file
     * @link https://php.net/manual/en/function.mime-content-type.php
     * @param string $filename <p>
     * Path to the tested file.
     * </p>
     * @return string the content type in MIME format, like
     * text/plain or application/octet-stream.
     */
    function mime_content_type ($filename);
}
