<?php
//cgxlm
namespace Payment\Common\Weixin\Data;

class RefundData extends WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('appid' => $this->appId, 'mch_id' => $this->mchId, 'device_info' => $this->terminal_id, 'nonce_str' => $this->nonceStr, 'refund_fee_type' => $this->feeType, 'transaction_id' => $this->transaction_id, 'out_trade_no' => $this->out_trade_no, 'out_refund_no' => $this->refund_no, 'total_fee' => $this->total_fee, 'refund_fee' => $this->refund_fee, 'op_user_id' => $this->operator_id, 'refund_account' => $this->refund_account);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($this->retData);
	}

	protected function checkDataParam()
	{
		$refundNo = $this->refund_no;
		$transactionId = $this->transaction_id;
		$outTradeNo = $this->out_trade_no;
		$totalFee = $this->total_fee;
		$refundFee = $this->refund_fee;
		$operatorId = $this->operator_id;
		$refundAccount = $this->refund_account;

		if (empty($refundNo)) {
			throw new \Payment\Common\PayException('请设置退款单号 refund_no');
		}

		if (empty($transactionId) && empty($outTradeNo)) {
			throw new \Payment\Common\PayException('必须提供微信交易号或商户网站唯一订单号。建议使用微信交易号');
		}

		$this->total_fee = bcmul($totalFee, 100, 0);
		$this->refund_fee = bcmul($refundFee, 100, 0);

		if (bccomp($refundFee, $totalFee, 2) === 1) {
			throw new \Payment\Common\PayException('退款金额不能大于订单总金额');
		}

		if (!in_array($refundAccount, array(\Payment\Common\WxConfig::REFUND_RECHARGE, \Payment\Common\WxConfig::REFUND_UNSETTLED))) {
			$this->refund_account = \Payment\Common\WxConfig::REFUND_UNSETTLED;
		}

		$certPath = $this->appCertPem;
		$keyPath = $this->appKeyPem;

		if (empty($certPath)) {
			throw new \Payment\Common\PayException('退款接口，必须提供 apiclient_cert.pem 证书');
		}

		if (empty($keyPath)) {
			throw new \Payment\Common\PayException('退款接口，必须提供 apiclient_key.pem 证书');
		}

		if (empty($operatorId)) {
			$this->operator_id = $this->mchId;
		}
	}
}

?>
