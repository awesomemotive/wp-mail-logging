<?php

namespace No3x\WPML\Renderer\Column;

use No3x\WPML\Renderer\Exception\ColumnDoesntExistException;

class SubjectColumn implements IColumn {
    protected $column_name;

	const EMAIL_SUBJECT_BASE64_ENCODED = '=?utf-8?B?';
	const EMAIL_SUBJECT_QUOTED_ENCODED = '=?utf-8?Q?';

    /**
     * GenericColumn constructor.
     * @param $column_name
     */
    public function __construct($column_name) {
        $this->column_name = $column_name;
    }

    /**
     * @inheritdoc
     */
    public function render(array $mailArray, $format) {
        if( ! array_key_exists($this->column_name, $mailArray) ) {
            throw new ColumnDoesntExistException($this->column_name);
        }

        $subject = $mailArray[$this->column_name];

		if( 0 === strpos($subject, self::EMAIL_SUBJECT_BASE64_ENCODED)) {
			$subject_encoded = substr($subject, strlen(self::EMAIL_SUBJECT_BASE64_ENCODED), strlen($subject) - strlen(self::EMAIL_SUBJECT_BASE64_ENCODED) - 1);
			$subject_decoded = base64_decode($subject_encoded);
			return '[Base64] ' . $subject_decoded;
		} else if ( 0 === strpos($subject, self::EMAIL_SUBJECT_QUOTED_ENCODED) ){
			$subject_encoded = substr($subject, strlen(self::EMAIL_SUBJECT_QUOTED_ENCODED), strlen($subject) - strlen(self::EMAIL_SUBJECT_QUOTED_ENCODED) - 1);
			$subject_decoded = quoted_printable_decode($subject_encoded);
			return '[Quoted Printable] ' . $subject_decoded;
		}
		return $subject;
    }
}
