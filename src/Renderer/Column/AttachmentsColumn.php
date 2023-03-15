<?php

namespace No3x\WPML\Renderer\Column;


use No3x\WPML\WPML_Attachment;
use No3x\WPML\WPML_Utils;

class AttachmentsColumn extends GenericColumn {

    /**
     * TimestampColumn constructor.
     */
    public function __construct() {
        parent::__construct("attachments");
    }

    /**
     * @inheritdoc
     */
    public function render(array $mailArray, $format) {
        if($format == ColumnFormat::SIMPLE) {
            return parent::render($mailArray, $format);
        } elseif ($format == ColumnFormat::FULL) {
            return $this->column_attachments($mailArray);
        }
        throw new \Exception("Unknown Format");
    }

    /**
     * Renders the attachment column in compat mode for mails prior 1.6.0.
     * @since 1.6.0
     * @param array $item The current item.
     * @return string The attachment column.
     */
    function column_attachments_compat_152( $item ) {
        $attachment_append = '';
        $attachments = explode( ',\n', $item['attachments'] );
        $attachments = is_array( $attachments ) ? $attachments : array( $attachments );
        foreach ( $attachments as $attachment ) {
            // $attachment can be an empty string ''.
            if ( ! empty( $attachment ) ) {
                $filename = basename( $attachment );
                $attachment_path = WP_CONTENT_DIR . $attachment;
                $attachment_url = WP_CONTENT_URL . $attachment;
                if ( is_file( $attachment_path ) ) {
                    $attachment_append .= '<a target="_blank" href="' . $attachment_url . '" title="' . $filename . '">' . WPML_Utils::generate_attachment_icon( $attachment_path ) . '</a> ';
                } else {
                    /* translators: %s filename of the attachment that doesn't exist. */
                    $message = sprintf( __( 'Attachment %s is not present', 'wp-mail-logging' ), $filename );
                    $attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
                }
            }
        }
        return $attachment_append;
    }
    /**
     * Renders the attachment column.
     * @since 1.3
     * @param array $item The current item.
     * @return string The attachment column.
     */
    function column_attachments( $item ) {

        if ( version_compare( trim( $item ['plugin_version'] ), '1.6.0', '<' ) ) {
            return $this->column_attachments_compat_152( $item );
        }

        $attachment_append = '';
        $attachmentRelPaths = explode( ',\n', $item['attachments'] );
        $attachmentRelPaths = is_array( $attachmentRelPaths ) ? $attachmentRelPaths : array( $attachmentRelPaths );
        $attachmentRelPaths = array_filter($attachmentRelPaths);

        foreach ( $attachmentRelPaths as $attachmentRelPath ) {

            $attachment = WPML_Attachment::fromRelPath($attachmentRelPath);

            if ( !$attachment->isGone() ) {
                $attachment_append .= '<a target="_blank" href="' . $attachment->getUrl() . '" title="' . $attachment->getFileName() . '">' . WPML_Utils::generate_attachment_icon( $attachment ) . '</a> ';
            } else {
                $message = sprintf( __( 'Attachment %s is not present', 'wp-mail-logging' ), $attachment->getFileName() );
                $attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
            }
        }

        return $attachment_append;
    }

}

