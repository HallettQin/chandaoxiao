<?php
//cgxlm
namespace Payment\Common\Ali\Data;

abstract class AliBaseData extends \Payment\Common\BaseData
{
	public function getData()
	{
		$data = parent::getData();
		$data = \Payment\Utils\ArrayUtil::arraySort($data);
		return $data;
	}

	protected function makeSign($signStr)
	{
		switch ($this->signType) {
		case 'RSA':
			$rsa = new \Payment\Utils\RsaEncrypt($this->rsaPrivateKey);
			$sign = $rsa->encrypt($signStr);
			break;

		case 'RSA2':
			$rsa = new \Payment\Utils\Rsa2Encrypt($this->rsaPrivateKey);
			$sign = $rsa->encrypt($signStr);
			break;

		default:
			$sign = '';
		}

		return $sign;
	}
}

?>
