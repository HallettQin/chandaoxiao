<?php
//cgxlm
namespace Payment\Common\Ali\Data\Charge;

class QrChargeData extends ChargeBaseData
{
	protected function getBizContent()
	{
		$content = array('body' => strval($this->body), 'subject' => strval($this->subject), 'out_trade_no' => strval($this->order_no), 'total_amount' => strval($this->amount), 'seller_id' => $this->partner, 'store_id' => $this->store_id, 'operator_id' => $this->operator_id, 'terminal_id' => $this->terminal_id, 'alipay_store_id' => $this->alipay_store_id);
		$timeExpire = $this->timeout_express;

		if (!empty($timeExpire)) {
			$express = floor(($timeExpire - strtotime($this->timestamp)) / 60);
			$express && $content['timeout_express'] = $express . 'm';
		}

		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}
}

?>
