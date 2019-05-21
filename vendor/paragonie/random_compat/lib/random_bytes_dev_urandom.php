<?php
//cgxlm
if (!defined('RANDOM_COMPAT_READ_BUFFER')) {
	define('RANDOM_COMPAT_READ_BUFFER', 8);
}

if (!is_callable('random_bytes')) {
	function random_bytes($bytes)
	{
		static $fp;

		if (empty($fp)) {
			$fp = fopen('/dev/urandom', 'rb');

			if (!empty($fp)) {
				$st = fstat($fp);

				if (($st['mode'] & 61440) !== 8192) {
					fclose($fp);
					$fp = false;
				}
			}

			if (!empty($fp)) {
				if (is_callable('stream_set_read_buffer')) {
					stream_set_read_buffer($fp, RANDOM_COMPAT_READ_BUFFER);
				}

				if (is_callable('stream_set_chunk_size')) {
					stream_set_chunk_size($fp, RANDOM_COMPAT_READ_BUFFER);
				}
			}
		}

		try {
			$bytes = RandomCompat_intval($bytes);
		}
		catch (TypeError $ex) {
			throw new TypeError('random_bytes(): $bytes must be an integer');
		}

		if ($bytes < 1) {
			throw new Error('Length must be greater than 0');
		}

		if (!empty($fp)) {
			$remaining = $bytes;
			$buf = '';

			do {
				$read = fread($fp, $remaining);

				if (!is_string($read)) {
					if ($read === false) {
						$buf = false;
						break;
					}
				}

				$remaining -= RandomCompat_strlen($read);
				$buf = $buf . $read;
			} while (0 < $remaining);

			if (is_string($buf)) {
				if (RandomCompat_strlen($buf) === $bytes) {
					return $buf;
				}
			}
		}

		throw new Exception('Error reading from source device');
	}
}

?>
