<?php

namespace No3x\WPML\Renderer\Column;

use No3x\WPML\Renderer\Exception\ColumnDoesntExistException;
use No3x\WPML\Renderer\WPML_ColumnManager;

class SubjectColumn extends GenericColumn {

    /**
     * Base64 encoding prefix.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const EMAIL_SUBJECT_BASE64_ENCODED = '=?utf-8?B?';

    /**
     * Quoted-printable encoding prefix.
     *
     * @since 1.12.0
     *
     * @var string
     */
    const EMAIL_SUBJECT_QUOTED_ENCODED = '=?utf-8?Q?';

    /**
     * The email subject.
     *
     * @since 1.12.0
     *
     * @var string
     */
    protected $subject = '';

    public function __construct() {

        parent::__construct( WPML_ColumnManager::COLUMN_SUBJECT );
    }

    /**
     * @inerhitDoc
     *
     * @since 1.12.0
     */
    public function render( $item, $column_format ) {

        if ( ! array_key_exists( $this->column_name, $item ) ) {
            throw new ColumnDoesntExistException($this->column_name);
        }

        if ( empty( $item[ $this->column_name ] ) ) {
            return '';
        }

        $this->subject = $item[ $this->column_name ];

        if ( strpos( $this->subject, self::EMAIL_SUBJECT_BASE64_ENCODED ) === 0 ) {
            return base64_decode( $this->get_encoded_subject( self::EMAIL_SUBJECT_BASE64_ENCODED ) );
        }

        if ( strpos( $this->subject, self::EMAIL_SUBJECT_QUOTED_ENCODED ) === 0 ) {
            return quoted_printable_decode( $this->get_encoded_subject( self::EMAIL_SUBJECT_QUOTED_ENCODED ) );
        }

        return esc_html( $this->subject );
    }

    /**
     * Get the encoded part from the subject string.
     *
     * @since 1.12.0
     *
     * @param string $encode Type of encoding used in the subject.
     *
     * @return false|string
     */
    private function get_encoded_subject( $encode ) {

        $encode_len      = strlen( $encode );
        $encoded_subject = substr( $this->subject, $encode_len, strlen( $this->subject ) - $encode_len - 1 );

        return $encoded_subject;
    }
}
