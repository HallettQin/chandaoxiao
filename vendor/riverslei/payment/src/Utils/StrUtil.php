<?php
//cgxlm
namespace Payment\Utils;

class StrUtil
{
	static public function getNonceStr($length = 32)
	{
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$str = '';

		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}

		return $str;
	}

	static public function characet($str, $targetCharset)
	{
		if (empty($str)) {
			return $str;
		}

		if (strcasecmp('UTF-8', $targetCharset) != 0) {
			$str = mb_convert_encoding($str, $targetCharset, 'UTF-8');
		}

		return $str;
	}

	static public function String2Hex($string)
	{
		$hex = '';
		$len = strlen($string);

		for ($i = 0; $i < $len; $i++) {
			$hex .= dechex(ord($string[$i]));
		}

		return $hex;
	}

	static public function getRsaKeyValue($key, $type = 'private')
	{
		if (is_file($key)) {
			$keyStr = @file_get_contents($key);
		}
		else {
			$keyStr = $key;
		}

		$keyStr = str_replace(PHP_EOL, '', $keyStr);

		if ($type === 'private') {
			$beginStr = array('-----BEGIN RSA PRIVATE KEY-----', '-----BEGIN PRIVATE KEY-----');
			$endStr = array('-----END RSA PRIVATE KEY-----', '-----END PRIVATE KEY-----');
		}
		else {
			$beginStr = array('-----BEGIN PUBLIC KEY-----', '');
			$endStr = array('-----END PUBLIC KEY-----', '');
		}

		$keyStr = str_replace($beginStr, array('', ''), $keyStr);
		$keyStr = str_replace($endStr, array('', ''), $keyStr);
		$rsaKey = $beginStr[0] . PHP_EOL . wordwrap($keyStr, 64, PHP_EOL, true) . PHP_EOL . $endStr[0];
		return $rsaKey;
	}
}


?>
