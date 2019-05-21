<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Query;

class RefundQueryData extends \Payment\Common\Weixin\Data\WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('appid' => $this->appId, 'mch_id' => $this->mchId, 'device_info' => $this->terminal_id, 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'transaction_id' => $this->transaction_id, 'out_trade_no' => $this->out_trade_no, 'out_refund_no' => $this->refund_no, 'refund_id' => $this->refund_id);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($this->retData);
	}

	protected function checkDataParam()
	{
		$transactionId = $this->transaction_id;
		$orderNo = $this->out_trade_no;
		$refundNo = $this->refund_no;
		$refundId = $this->refund_id;
		if (empty($transactionId) && empty($orderNo) && empty($refundNo) && empty($refundId)) {
			throw new \Payment\Common\PayException('查询退款  必须提供微信交易号、商户订单号、商户退款单号、微信退款交易号中的一种');
		}
	}
}

?>
