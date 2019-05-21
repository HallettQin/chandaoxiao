<?php
//cgxlm
namespace Payment\Utils;

class ArrayUtil
{
	static public function paraFilter($para)
	{
		$paraFilter = array();

		while (list($key, $val) = each($para)) {
			if (($val === '') || ($val === null)) {
				continue;
			}
			else {
				if (!is_array($para[$key])) {
					$para[$key] = is_bool($para[$key]) ? $para[$key] : trim($para[$key]);
				}

				$paraFilter[$key] = $para[$key];
			}
		}

		return $paraFilter;
	}

	static public function removeKeys(array $inputs, $keys)
	{
		if (!is_array($keys)) {
			$keys = explode(',', $keys);
		}

		if (empty($keys) || !is_array($keys)) {
			return $inputs;
		}

		$flag = true;

		foreach ($keys as $key) {
			if (array_key_exists($key, $inputs)) {
				if (is_int($key)) {
					$flag = false;
				}

				unset($inputs[$key]);
			}
		}

		if (!$flag) {
			$inputs = array_values($inputs);
		}

		return $inputs;
	}

	static public function arraySort(array $param)
	{
		ksort($param);
		reset($param);
		return $param;
	}

	static public function createLinkstring($para)
	{
		if (!is_array($para)) {
			throw new \Exception('必须传入数组参数');
		}

		reset($para);
		$arg = '';

		while (list($key, $val) = each($para)) {
			if (is_array($val)) {
				continue;
			}

			$arg .= $key . '=' . urldecode($val) . '&';
		}

		$arg && ($arg = substr($arg, 0, -1));

		if (get_magic_quotes_gpc()) {
			$arg = stripslashes($arg);
		}

		return $arg;
	}
}


?>
