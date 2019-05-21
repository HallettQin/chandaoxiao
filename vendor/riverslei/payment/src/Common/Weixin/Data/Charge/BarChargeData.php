<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Charge;

class BarChargeData extends ChargeBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$authCode = $this->auth_code;

		if (empty($authCode)) {
			throw new \Payment\Common\PayException('扫码支付授权码,必须设置该参数.');
		}
	}

	protected function buildData()
	{
		$signData = array('appid' => trim($this->appId), 'mch_id' => trim($this->mchId), 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'fee_type' => $this->feeType, 'device_info' => $this->terminal_id, 'body' => trim($this->subject), 'attach' => trim($this->return_param), 'out_trade_no' => trim($this->order_no), 'total_fee' => $this->amount, 'spbill_create_ip' => trim($this->client_ip), 'auth_code' => $this->auth_code, 'sub_appid' => $this->sub_appid, 'sub_mch_id' => $this->sub_mch_id);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}
}

?>
