<?php
//cgxlm
if (!is_callable('random_int')) {
	function random_int($min, $max)
	{
		try {
			$min = RandomCompat_intval($min);
		}
		catch (TypeError $ex) {
			throw new TypeError('random_int(): $min must be an integer');
		}

		try {
			$max = RandomCompat_intval($max);
		}
		catch (TypeError $ex) {
			throw new TypeError('random_int(): $max must be an integer');
		}

		if ($max < $min) {
			throw new Error('Minimum value must be less than or equal to the maximum value');
		}

		if ($max === $min) {
			return $min;
		}

		$attempts = $bits = $bytes = $mask = $valueShift = 0;
		$range = $max - $min;

		if (!is_int($range)) {
			$bytes = PHP_INT_SIZE;
			$mask = ~0;
		}
		else {
			while (0 < $range) {
				if (($bits % 8) === 0) {
					++$bytes;
				}

				++$bits;
				$range >>= 1;
				$mask = ($mask << 1) | 1;
			}

			$valueShift = $min;
		}

		$val = 0;

		do {
			if (128 < $attempts) {
				throw new Exception('random_int: RNG is broken - too many rejections');
			}

			$randomByteString = random_bytes($bytes);
			$val &= 0;

			for ($i = 0; $i < $bytes; ++$i) {
				$val |= ord($randomByteString[$i]) << ($i * 8);
			}

			$val &= $mask;
			$val += $valueShift;
			++$attempts;
		} while (!is_int($val) || ($max < $val) || ($val < $min));

		return (int) $val;
	}
}

?>
