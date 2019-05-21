<?php
//cgxlm
namespace Payment\Common\Cmb\Data\Query;

class RefundQueryData extends \Payment\Common\Cmb\Data\CmbBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$orderNo = $this->out_trade_no;
		$date = $this->date;
		$refundId = $this->refund_id;
		$refundNo = $this->refund_no;

		if (empty($date)) {
			throw new \Payment\Common\PayException('商户退款日期，格式：yyyyMMdd');
		}

		if (!empty($refundId)) {
			$this->out_trade_no = '';
			$this->refund_no = '';
			$this->type = 'A';
		}
		else {
			if (!empty($orderNo) && !empty($refundNo)) {
				$this->refund_id = '';
				$this->type = 'B';
			}
			else if (!empty($orderNo)) {
				$this->refund_id = '';
				$this->refund_no = '';
				$this->type = 'C';
			}
			else {
				throw new \Payment\Common\PayException('请设置需要查询的商户订单号');
			}
		}
	}

	protected function getReqData()
	{
		$reqData = array('dateTime' => $this->dateTime, 'branchNo' => $this->branchNo, 'merchantNo' => $this->merchantNo, 'type' => $this->type, 'orderNo' => $this->out_trade_no ? $this->out_trade_no : '', 'date' => $this->date, 'merchantSerialNo' => $this->refund_no ? $this->refund_no : '', 'bankSerialNo' => $this->refund_id ? $this->refund_id : '');
		return $reqData;
	}
}

?>
