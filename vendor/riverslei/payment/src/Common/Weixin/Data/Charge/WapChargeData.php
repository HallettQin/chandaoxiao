<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Charge;

class WapChargeData extends ChargeBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$info = $this->scene_info;
		if (!is_array($info) || empty($info)) {
			throw new \Payment\Common\PayException('微信 H5 支付，必须提供该参数');
		}
	}

	protected function buildData()
	{
		$info = $this->scene_info;
		$sceneInfo['h5_info'] = $info;
		$signData = array('appid' => trim($this->appId), 'mch_id' => trim($this->mchId), 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'fee_type' => $this->feeType, 'notify_url' => $this->notifyUrl, 'trade_type' => $this->tradeType, 'limit_pay' => $this->limitPay, 'device_info' => $this->terminal_id, 'body' => trim($this->subject), 'attach' => trim($this->return_param), 'out_trade_no' => trim($this->order_no), 'total_fee' => $this->amount, 'spbill_create_ip' => trim($this->client_ip), 'time_start' => $this->timeStart, 'time_expire' => $this->timeout_express, 'scene_info' => json_encode($sceneInfo));
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}
}

?>
