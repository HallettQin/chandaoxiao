<?php
//cgxlm
if (!is_callable('RandomCompat_strlen')) {
	if (defined('MB_OVERLOAD_STRING') && (ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING)) {
		function RandomCompat_strlen($binary_string)
		{
			if (!is_string($binary_string)) {
				throw new TypeError('RandomCompat_strlen() expects a string');
			}

			return (int) mb_strlen($binary_string, '8bit');
		}
	}
	else {
		function RandomCompat_strlen($binary_string)
		{
			if (!is_string($binary_string)) {
				throw new TypeError('RandomCompat_strlen() expects a string');
			}

			return (int) strlen($binary_string);
		}
	}
}

if (!is_callable('RandomCompat_substr')) {
	if (defined('MB_OVERLOAD_STRING') && (ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING)) {
		function RandomCompat_substr($binary_string, $start, $length = NULL)
		{
			if (!is_string($binary_string)) {
				throw new TypeError('RandomCompat_substr(): First argument should be a string');
			}

			if (!is_int($start)) {
				throw new TypeError('RandomCompat_substr(): Second argument should be an integer');
			}

			if ($length === NULL) {
				$length = RandomCompat_strlen($binary_string) - $start;
			}
			else if (!is_int($length)) {
				throw new TypeError('RandomCompat_substr(): Third argument should be an integer, or omitted');
			}

			if (($start === RandomCompat_strlen($binary_string)) && ($length === 0)) {
				return '';
			}

			if (RandomCompat_strlen($binary_string) < $start) {
				return '';
			}

			return (string) mb_substr($binary_string, $start, $length, '8bit');
		}
	}
	else {
		function RandomCompat_substr($binary_string, $start, $length = NULL)
		{
			if (!is_string($binary_string)) {
				throw new TypeError('RandomCompat_substr(): First argument should be a string');
			}

			if (!is_int($start)) {
				throw new TypeError('RandomCompat_substr(): Second argument should be an integer');
			}

			if ($length !== NULL) {
				if (!is_int($length)) {
					throw new TypeError('RandomCompat_substr(): Third argument should be an integer, or omitted');
				}

				return (string) substr($binary_string, $start, $length);
			}

			return (string) substr($binary_string, $start);
		}
	}
}

?>
