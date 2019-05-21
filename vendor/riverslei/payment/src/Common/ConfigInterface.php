<?php
//cgxlm
namespace Payment\Common;

abstract class ConfigInterface
{
	public $returnRaw = true;
	public $useSandbox = true;
	public $limitPay;
	public $notifyUrl;
	public $signType = 'RSA';

	public function toArray()
	{
		return get_object_vars($this);
	}
}


?>
