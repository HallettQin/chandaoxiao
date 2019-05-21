<?php
//cgxlm
namespace Payment\Utils;

class RsaEncrypt
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

		openssl_sign($data, $sign, $res);
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

		$content = base64_decode($content);
		$result = '';

		for ($i = 0; $i < (strlen($content) / 128); $i++) {
			$data = substr($content, $i * 128, 128);
			openssl_private_decrypt($data, $decrypt, $res);
			$result .= $decrypt;
		}

		openssl_free_key($res);
		return $result;
	}

	public function rsaVerify($data, $sign)
	{
		$res = openssl_get_publickey($this->key);

		if (empty($res)) {
			throw new \Exception('支付宝RSA公钥错误。请检查公钥文件格式是否正确');
		}

		$result = (bool) openssl_verify($data, base64_decode($sign), $res);
		openssl_free_key($res);
		return $result;
	}
}


?>
