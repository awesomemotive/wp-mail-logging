<?php

namespace No3x\WPML\Renderer\Column;

use No3x\WPML\Renderer\Exception\ColumnDoesntExistException;
use No3x\WPML\Renderer\WPML_ColumnManager;

/**
 * Renders the receiver column for display.
 *
 * WPML_MailExtractor joins multiple receivers with the literal two-character sequence `\n`
 * preceded by a comma. That literal is a de-facto internal delimiter: WPML_Email_Resender
 * splits on it to recover the individual addresses, and rows logged by earlier versions
 * already contain it. It therefore stays in the database and is normalized here, at
 * display time, so historical rows are covered too.
 *
 * @since {VERSION}
 */
class ReceiverColumn extends GenericColumn {

    /**
     * ReceiverColumn constructor.
     *
     * @since {VERSION}
     */
    public function __construct() {

        parent::__construct( WPML_ColumnManager::COLUMN_RECEIVER );
    }

    /**
     * Render the receiver column with the internal delimiter replaced by a comma and a space.
     *
     * @since {VERSION}
     *
     * @param array  $mailArray The current item.
     * @param string $format    The column format.
     *
     * @return string The comma separated list of receivers.
     *
     * @throws ColumnDoesntExistException When the column is not present on the item.
     */
    public function render( array $mailArray, $format ) {

        return self::normalize( parent::render( $mailArray, $format ) );
    }

    /**
     * Replace the internal receiver delimiter with a comma and a space.
     *
     * Handles the literal `\n` and `\r\n` sequences written by the extractor as well as
     * real newlines, so that both current and historical rows render consistently.
     *
     * @since {VERSION}
     *
     * @param string $receiver The raw receiver value as stored.
     *
     * @return string
     */
    public static function normalize( $receiver ) {

        if ( ! is_string( $receiver ) || $receiver === '' ) {
            return '';
        }

        $normalized = str_replace(
            [ '\r\n', '\n', "\r\n" ],
            "\n",
            $receiver
        );

        $receivers = array_filter(
            array_map(
                function ( $single_receiver ) {
                    return trim( rtrim( trim( $single_receiver ), ',' ) );
                },
                explode( "\n", $normalized )
            ),
            'strlen'
        );

        return implode( ', ', $receivers );
    }
}
