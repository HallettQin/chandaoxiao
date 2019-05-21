<?php
namespace Payment\Utils;

class Rsa2Encrypt
{
	protected $key;

	public function __construct($key)
	{
		$this->key = $key;
	}

	public function setKey($key)
	{
		$this->key = $key;
	}

	public function encrypt($data)
	{
		if ($this->key === false) {
			return '';
		}

		$res = openssl_get_privatekey($this->key);

		if (empty($res)) {
			throw new \Exception('您使用的私钥格式错误，请检查RSA私钥配置');
		}

		openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
		openssl_free_key($res);
		$sign = base64_encode($sign);
		return $sign;
	}

	public function decrypt($content)
	{
		if ($this->key === false) {
			return '';
		}

		$res = openssl_get_privatekey($this->key);

		if (empty($res)) {
			throw new \Exception('您使用的私钥格式错误，请检查RSA私钥配置');
		}

		$decodes = base64_decode($content);
		$str = '';
		$dcyCont = '';

		foreach ($decodes as $n => $decode) {
			if (!openssl_private_decrypt($decode, $dcyCont, $res)) {
				echo '<br/>' . openssl_error_string() . '<br/>';
			}

			$str .= $dcyCont;
		}

		openssl_free_key($res);
		return $str;
	}

	public function rsaVerify($data, $sign)
	{
		$res = openssl_get_publickey($this->key);

		if (empty($res)) {
			throw new \Exception('支付宝RSA公钥错误。请检查公钥文件格式是否正确');
		}

		$result = (bool) openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
		openssl_free_key($res);
		return $result;
	}
}


?>
