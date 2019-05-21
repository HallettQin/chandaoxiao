<?php
//cgxlm
namespace Payment\Common\Ali\Data\Charge;

class WapChargeData extends ChargeBaseData
{
	protected function getBizContent()
	{
		$content = array('body' => strval($this->body), 'subject' => strval($this->subject), 'out_trade_no' => strval($this->order_no), 'total_amount' => strval($this->amount), 'seller_id' => $this->partner, 'product_code' => 'QUICK_WAP_PAY', 'goods_type' => $this->goods_type, 'passback_params' => $this->return_param, 'disable_pay_channels' => $this->limitPay, 'store_id' => $this->store_id);
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
