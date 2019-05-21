<?php
//cgxlm
if (!is_callable('random_bytes')) {
	function random_bytes($bytes)
	{
		try {
			$bytes = RandomCompat_intval($bytes);
		}
		catch (TypeError $ex) {
			throw new TypeError('random_bytes(): $bytes must be an integer');
		}

		if ($bytes < 1) {
			throw new Error('Length must be greater than 0');
		}

		$buf = @mcrypt_create_iv($bytes, MCRYPT_DEV_URANDOM);
		if (($buf !== false) && (RandomCompat_strlen($buf) === $bytes)) {
			return $buf;
		}

		throw new Exception('Could not gather sufficient random data');
	}
}

?>
