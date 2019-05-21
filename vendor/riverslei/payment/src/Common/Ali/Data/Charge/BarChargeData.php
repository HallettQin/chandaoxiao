<?php
//cgxlm
namespace Payment\Common\Ali\Data\Charge;

class BarChargeData extends ChargeBaseData
{
	protected function getBizContent()
	{
		$content = array('body' => strval($this->body), 'subject' => strval($this->subject), 'out_trade_no' => strval($this->order_no), 'total_amount' => strval($this->amount), 'seller_id' => $this->partner, 'store_id' => $this->store_id, 'operator_id' => $this->operator_id, 'terminal_id' => $this->terminal_id, 'alipay_store_id' => $this->alipay_store_id, 'scene' => $this->scene, 'auth_code' => $this->auth_code);
		$timeExpire = $this->timeout_express;

		if (!empty($timeExpire)) {
			$express = floor(($timeExpire - strtotime($this->timestamp)) / 60);
			$express && $content['timeout_express'] = $express . 'm';
		}

		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}

	protected function checkDataParam()
	{
		parent::checkDataParam();
		$scene = $this->scene;
		$authCode = $this->auth_code;
		if (empty($scene) || !in_array($scene, array('bar_code', 'wave_code'))) {
			throw new \Payment\Common\PayException('支付场景 scene 必须设置 条码支付：bar_code 声波支付：wave_code');
		}

		if (empty($authCode)) {
			throw new \Payment\Common\PayException('请提供支付授权码');
		}
	}
}

?>
