<?php
//cgxlm
namespace Payment\Common\Weixin\Data;

class BackAppChargeData extends WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('appid' => $this->appId, 'partnerid' => $this->mchId, 'prepayid' => $this->prepay_id, 'package' => 'Sign=WXPay', 'noncestr' => $this->nonceStr, 'timestamp' => time());
	}

	protected function checkDataParam()
	{
	}
}

?>
