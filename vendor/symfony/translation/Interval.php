<?php
//cgxlm
namespace Symfony\Component\Translation;

class Interval
{
	static public function test($number, $interval)
	{
		$interval = trim($interval);

		if (!preg_match('/^' . self::getIntervalRegexp() . '$/x', $interval, $matches)) {
			throw new Exception\InvalidArgumentException(sprintf('"%s" is not a valid interval.', $interval));
		}

		if ($matches[1]) {
			foreach (explode(',', $matches[2]) as $n) {
				if ($number == $n) {
					return true;
				}
			}
		}
		else {
			$leftNumber = self::convertNumber($matches['left']);
			$rightNumber = self::convertNumber($matches['right']);
			return ('[' === $matches['left_delimiter'] ? $leftNumber <= $number : $leftNumber < $number) && (']' === $matches['right_delimiter'] ? $number <= $rightNumber : $number < $rightNumber);
		}

		return false;
	}

	static public function getIntervalRegexp()
	{
		return "        ({\\s*\n            (\\-?\\d+(\\.\\d+)?[\\s*,\\s*\\-?\\d+(\\.\\d+)?]*)\n        \\s*})\n\n            |\n\n        (?P<left_delimiter>[\\[\\]])\n            \\s*\n            (?P<left>-Inf|\\-?\\d+(\\.\\d+)?)\n            \\s*,\\s*\n            (?P<right>\\+?Inf|\\-?\\d+(\\.\\d+)?)\n            \\s*\n        (?P<right_delimiter>[\\[\\]])";
	}

	static private function convertNumber($number)
	{
		if ('-Inf' === $number) {
			return log(0);
		}
		else {
			if (('+Inf' === $number) || ('Inf' === $number)) {
				return 0 - log(0);
			}
		}

		return (double) $number;
	}
}


?>
