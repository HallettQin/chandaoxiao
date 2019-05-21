<?php
//cgxlm
namespace Payment\Charge\Wx;

class WxQrCharge extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->tradeType = 'NATIVE';
		return 'Payment\\Common\\Weixin\\Data\\Charge\\QrChargeData';
	}

	protected function retData(array $ret)
	{
		if ($this->config->returnRaw) {
			return $ret;
		}

		return $ret['code_url'];
	}
}

?>
