<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Charge;

class QrChargeData extends ChargeBaseData
{
	protected function buildData()
	{
		$signData = array('appid' => trim($this->appId), 'mch_id' => trim($this->mchId), 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'fee_type' => $this->feeType, 'notify_url' => $this->notifyUrl, 'trade_type' => $this->tradeType, 'limit_pay' => $this->limitPay, 'device_info' => $this->terminal_id, 'body' => trim($this->subject), 'attach' => trim($this->return_param), 'out_trade_no' => trim($this->order_no), 'total_fee' => $this->amount, 'spbill_create_ip' => trim($this->client_ip), 'time_start' => $this->timeStart, 'time_expire' => $this->timeout_express, 'openid' => $this->openid, 'product_id' => $this->product_id, 'sub_appid' => $this->sub_appid, 'sub_mch_id' => $this->sub_mch_id);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}

	protected function checkDataParam()
	{
		parent::checkDataParam();
		$productId = $this->product_id;

		if (empty($productId)) {
			throw new \Payment\Common\PayException('扫码支付,必须设置商品ID.');
		}
	}
}

?>
