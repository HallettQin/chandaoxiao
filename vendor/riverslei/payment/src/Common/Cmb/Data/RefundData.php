<?php
//cgxlm
namespace Payment\Common\Cmb\Data;

class RefundData extends CmbBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$refundNo = $this->refund_no;
		$date = $this->date;
		$outTradeNo = $this->out_trade_no;
		$refundFee = $this->refund_fee;
		$operatorId = $this->operator_id;
		if (empty($date) || (mb_strlen($date) !== 8)) {
			throw new \Payment\Common\PayException('商户订单日期必须提供,格式：yyyyMMdd');
		}

		if (empty($outTradeNo)) {
			throw new \Payment\Common\PayException('必须提供商户网站唯一订单号。');
		}

		if (empty($refundNo) && (mb_strlen($refundNo) < 21)) {
			throw new \Payment\Common\PayException('退款流水号,商户生成，不能超过20位');
		}

		if (empty($refundFee) || !is_numeric($refundFee)) {
			throw new \Payment\Common\PayException('退款金额,格式xxxx.xx');
		}

		if (empty($operatorId)) {
			throw new \Payment\Common\PayException('必须提供 商户结账系统的操作员号');
		}
	}

	protected function getReqData()
	{
		$rc4 = new \Payment\Utils\Rc4Encrypt($this->merKey);
		$reqData = array('dateTime' => $this->dateTime, 'branchNo' => $this->branchNo, 'merchantNo' => $this->merchantNo, 'date' => $this->date, 'orderNo' => $this->out_trade_no, 'refundSerialNo' => trim($this->refund_no), 'amount' => $this->refund_fee, 'desc' => $this->reason, 'operatorNo' => $this->operator_id, 'encrypType' => 'RC4', 'pwd' => $rc4->encrypt($this->opPwd));
		return $reqData;
	}
}

?>
