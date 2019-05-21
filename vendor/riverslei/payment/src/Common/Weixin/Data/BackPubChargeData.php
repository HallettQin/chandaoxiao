<?php
//cgxlm
namespace Payment\Common\Weixin\Data;

class BackPubChargeData extends WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('appId' => $this->appId, 'timeStamp' => time() . '', 'nonceStr' => $this->nonceStr, 'package' => 'prepay_id=' . $this->prepay_id, 'signType' => 'MD5');
	}

	protected function checkDataParam()
	{
	}
}

?>
