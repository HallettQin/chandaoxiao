<?php
//cgxlm
namespace Payment\Common\Ali\Data\Query;

class RefundQueryData extends QueryBaseData
{
	protected function getBizContent()
	{
		$content = array('out_trade_no' => $this->out_trade_no, 'trade_no' => $this->trade_no, 'out_request_no' => $this->refund_no);
		$content = \Payment\Utils\ArrayUtil::paraFilter($content);
		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}

	protected function checkDataParam()
	{
		$tradeNo = $this->trade_no;
		$outTradeNo = $this->out_trade_no;
		if (empty($outTradeNo) && empty($tradeNo)) {
			throw new \Payment\Common\PayException('必须提供支付宝交易号或者商户网站唯一订单号。建议使用支付宝交易号');
		}

		$refundNo = $this->refund_no;

		if (empty($refundNo)) {
			throw new \Payment\Common\PayException('支付宝查询退款，必须传入提款的请求号。如果在退款请求时未传入，则该值为创建交易时的外部交易号');
		}
	}
}

?>
