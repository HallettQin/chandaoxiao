<?php
//cgxlm
namespace Payment\Charge\Ali;

class AliWebCharge extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::PC_PAY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Charge\\WebChargeData';
	}
}

?>
