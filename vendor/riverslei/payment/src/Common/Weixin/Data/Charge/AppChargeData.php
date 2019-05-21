<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Charge;

class AppChargeData extends ChargeBaseData
{
	protected function buildData()
	{
		$signData = array('appid' => trim($this->appId), 'mch_id' => trim($this->mchId), 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'fee_type' => $this->feeType, 'notify_url' => $this->notifyUrl, 'trade_type' => $this->tradeType, 'limit_pay' => $this->limitPay, 'device_info' => $this->terminal_id, 'body' => trim($this->subject), 'attach' => trim($this->return_param), 'out_trade_no' => trim($this->order_no), 'total_fee' => $this->amount, 'spbill_create_ip' => trim($this->client_ip), 'time_start' => $this->timeStart, 'time_expire' => $this->timeout_express);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}
}

?>
