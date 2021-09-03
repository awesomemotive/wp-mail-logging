<?php

namespace No3x\WPML;


use No3x\WPML\FS\Filesystem;
use No3x\WPML\FS\IFilesystem;

class WPML_Attachment {

    /** @var IFilesystem */
    private static $fs;

    private $path;
    private $url;
    private $iconClass = null;
    private $gone = true;

    /**
     * WPML_Attachment constructor.
     * @param $path string
     * @param $url string
     * @param $gone bool
     */
    public function __construct($path, $url, $gone)
    {
        $this->path = $path;
        $this->url = $url;
        $this->gone = $gone;
    }

    public static function getFS()
    {
        if (self::$fs == null) {
            self::$fs = new Filesystem();
        }

        return self::$fs;
    }

    public static function setFS($fs)
    {
        self::$fs = $fs;
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getIconClass() {
        if ($this->iconClass === null) {
            $this->iconClass = $this->determine_mime_icon_class($this->getPath());
        }

        return $this->iconClass;
    }

    /**
     * @return bool
     */
    public function isGone() {
        return $this->gone;
    }

    /**
     * @return string
     */
    public function getFileName() {
        return basename($this->getPath());
    }

    public static function fromAbsPath($absPath) {
        $gone = false; // assume it's there
        $url = "not supported";
        return new WPML_Attachment($absPath, $url, $gone);
    }

    public function toRelPath() {
        $basename_needle = '/uploads/';
        $posAttachmentInUploads = strrpos($this->getPath(), $basename_needle);
        if( false !== $posAttachmentInUploads ) {
            $path = substr($this->getPath(), $posAttachmentInUploads + strlen($basename_needle) - 1 );
        } else {
            // not found
            $path = basename($this->getPath());
        }

        return $path;
    }

    public static function fromRelPath($relPath) {
        $gone = true;
        $path = "";
        $url = "";

        // $relPath can be an empty string ''.
        if ( ! empty( $relPath ) ) {
            $basename = '/uploads';
            $path = WP_CONTENT_DIR . $basename . $relPath;
            $url = WP_CONTENT_URL . $basename . $relPath;

            if ( self::getFS()->is_file( $path ) ) {
                $gone = false;
            } else {
                $path = $relPath;
            }
        }

        return new WPML_Attachment($path, $url, $gone);
    }

    private function determine_mime_icon_class( $file_path ) {
        $defaultIconClass = 'file';

        if($this->gone) {
            return $defaultIconClass;
        }

        $supported = array(
            'archive' => array(
                'application/zip',
                'application/x-rar-compressed',
                'application/x-rar',
                'application/x-gzip',
                'application/x-msdownload',
                'application/x-msdownload',
                'application/vnd.ms-cab-compressed',
            ),
            'audio',
            'code' => array(
                'text/x-c',
                'text/x-c++',
            ),
            'excel' => array( 'application/vnd.ms-excel'
            ),
            'image', 'text', 'movie',
            'pdf' => array(
                'application/pdf',
            ),
            'photo', 'picture',
            'powerpoint' => array(
                'application/vnd.ms-powerpoint'
            ), 'sound', 'video', 'word' => array(
                'application/msword'
            ), 'zip'
        );

        if( !function_exists('mime_content_type') ) {
            return $defaultIconClass;
        }

        $mime = self::getFS()->mime_content_type( $file_path );

        if(false === $mime) {
            return $defaultIconClass;
        }
        $mime_parts = explode( '/', $mime );
        $attribute = $mime_parts[0];
        $type = $mime_parts[1];

        $iconClass = false;
        if ( ($key = WPML_Utils::recursive_array_search( $mime, $supported ) ) !== false ) {
            // Use specific icon class for mime first.
            $iconClass = $key;
        } elseif ( in_array( $attribute, $supported ) ) {
            // Use generic icon class.
            $iconClass = $attribute;
        }

        if ( false === $iconClass  ) {
            return $defaultIconClass;
        } else {
            return $iconClass;
        }
    }
}
