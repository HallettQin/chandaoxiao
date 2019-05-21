<?php
//cgxlm
namespace Payment\Charge\Wx;

class WxWapCharge extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->tradeType = 'MWEB';
		return 'Payment\\Common\\Weixin\\Data\\Charge\\WapChargeData';
	}

	protected function retData(array $ret)
	{
		if ($this->config->returnRaw) {
			return $ret;
		}

		$wabUrl = $ret['mweb_url'];

		if ($this->config->returnUrl) {
			$wabUrl .= '&redirect_url=' . urlencode($this->config->returnUrl);
		}

		return $wabUrl;
	}
}

?>
