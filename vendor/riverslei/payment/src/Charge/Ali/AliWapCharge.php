<?php
//cgxlm
namespace Payment\Charge\Ali;

class AliWapCharge extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::WAP_PAY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Charge\\WapChargeData';
	}
}

?>
