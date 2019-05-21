<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Charge;

class PubChargeData extends ChargeBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$openid = $this->openid;

		if (empty($openid)) {
			throw new \Payment\Common\PayException('用户在商户appid下的唯一标识,公众号支付,必须设置该参数.');
		}

		$subMchId = $this->sub_mch_id;
		$subOpenid = $this->sub_openid;
		if ($subMchId && empty($subOpenid)) {
			throw new \Payment\Common\PayException('公众号的服务商模式，必须提供 sub_openid 参数.');
		}
	}

	protected function buildData()
	{
		$signData = array('appid' => trim($this->appId), 'mch_id' => trim($this->mchId), 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'fee_type' => $this->feeType, 'notify_url' => $this->notifyUrl, 'trade_type' => $this->tradeType, 'limit_pay' => $this->limitPay, 'device_info' => $this->terminal_id, 'body' => trim($this->subject), 'attach' => trim($this->return_param), 'out_trade_no' => trim($this->order_no), 'total_fee' => $this->amount, 'spbill_create_ip' => trim($this->client_ip), 'time_start' => $this->timeStart, 'time_expire' => $this->timeout_express, 'openid' => $this->openid);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}
}

?>
