<?php
//cgxlm
namespace Payment\Common\Ali\Data;

class RefundData extends AliBaseData
{
	protected function checkDataParam()
	{
		$tradeNo = $this->trade_no;
		$outTradeNo = $this->out_trade_no;
		$refundAmount = $this->refund_fee;
		if (empty($outTradeNo) && empty($tradeNo)) {
			throw new \Payment\Common\PayException('必须提供支付宝交易号或者商户网站唯一订单号。建议使用支付宝交易号');
		}

		if (empty($refundAmount) || !is_numeric($refundAmount)) {
			throw new \Payment\Common\PayException('refund_fee 退款的金额，该金额不能大于订单金额,单位为元，支持两位小数');
		}
	}

	protected function buildData()
	{
		$signData = array('app_id' => $this->appId, 'method' => $this->method, 'format' => $this->format, 'charset' => $this->charset, 'sign_type' => $this->signType, 'timestamp' => $this->timestamp, 'version' => $this->version, 'biz_content' => $this->getBizContent());
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}

	private function getBizContent()
	{
		$content = array('out_trade_no' => $this->out_trade_no, 'trade_no' => $this->trade_no, 'refund_amount' => $this->refund_fee, 'refund_reason' => $this->reason, 'out_request_no' => $this->refund_no, 'operator_id' => $this->operator_id, 'store_id' => $this->store_id, 'terminal_id' => $this->terminal_id);
		$content = \Payment\Utils\ArrayUtil::paraFilter($content);
		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}
}

?>
