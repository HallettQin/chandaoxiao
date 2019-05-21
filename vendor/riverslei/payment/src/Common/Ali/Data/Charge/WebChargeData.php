<?php
//cgxlm
namespace Payment\Common\Ali\Data\Charge;

class WebChargeData extends ChargeBaseData
{
	protected function getBizContent()
	{
		$content = array('body' => strval($this->body), 'subject' => strval($this->subject), 'out_trade_no' => strval($this->order_no), 'total_amount' => strval($this->amount), 'product_code' => 'FAST_INSTANT_TRADE_PAY', 'goods_type' => $this->goods_type, 'passback_params' => $this->return_param, 'disable_pay_channels' => $this->limitPay, 'store_id' => $this->store_id, 'qr_pay_mode' => $this->qr_mod);
		$timeExpire = $this->timeout_express;

		if (!empty($timeExpire)) {
			$express = floor(($timeExpire - strtotime($this->timestamp)) / 60);
			$express && $content['timeout_express'] = $express . 'm';
		}

		$content = \Payment\Utils\ArrayUtil::paraFilter($content);
		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}
}

?>
