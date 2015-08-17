<?php
/**
 * Utils
 * @author No3x
 * @since 1.6.0
 */
class WPML_Utils {
	/**
	 * Ensure value is subset of given set
	 * @since 1.6.0
	 * @param $value expected value
	 * @param array $allowed_values allowed values
	 * @param string $default_value default value ()
	 * @return mixed
	 */
	public static function sanitize_expected_value( $value, $allowed_values, $default_value = null ) {
		$allowed_values = (is_array( $allowed_values) ) ? $allowed_values : array( $allowed_values );
		if($value && in_array( $value, $allowed_values))
			return $value;
		if(null != $default_value)
			return $default_value;
		return false;
	}
}
