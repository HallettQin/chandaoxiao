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

		$buf = '';

		if (!class_exists('COM')) {
			throw new Error('COM does not exist');
		}

		$util = new COM('CAPICOM.Utilities.1');
		$execCount = 0;

		do {
			$buf .= base64_decode($util->GetRandom($bytes, 0));

			if ($bytes <= RandomCompat_strlen($buf)) {
				return RandomCompat_substr($buf, 0, $bytes);
			}

			++$execCount;
		} while ($execCount < $bytes);

		throw new Exception('Could not gather sufficient random data');
	}
}

?>
