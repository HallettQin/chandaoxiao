<?php
//cgxlm
namespace Payment\Charge\Wx;

class WxAppCharge extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->tradeType = 'APP';
		return 'Payment\\Common\\Weixin\\Data\\Charge\\AppChargeData';
	}

	protected function retData(array $ret)
	{
		$back = new \Payment\Common\Weixin\Data\BackAppChargeData($this->config, $ret);
		$back->setSign();
		$backData = $back->getData();
		return $backData;
	}
}

?>
