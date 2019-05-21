<?php
//cgxlm
namespace Payment\Common\Cmb\Data\Query;

class ChargeQueryData extends \Payment\Common\Cmb\Data\CmbBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$bankSerialNo = $this->transaction_id;
		$date = $this->date;
		$orderNo = $this->out_trade_no;
		if (empty($date) || (mb_strlen($date) !== 8)) {
			throw new \Payment\Common\PayException('商户订单日期必须提供,格式：yyyyMMdd');
		}

		if ($bankSerialNo && (mb_strlen($bankSerialNo) === 20)) {
			$this->type = 'A';
		}
		else {
			if ($orderNo && (mb_strlen($bankSerialNo) <= 32)) {
				$this->type = 'B';
			}
			else {
				throw new \Payment\Common\PayException('必须设置商户订单信息或者招商流水号');
			}
		}
	}

	protected function getReqData()
	{
		$reqData = array('dateTime' => $this->dateTime, 'branchNo' => $this->branchNo, 'merchantNo' => $this->merchantNo, 'type' => $this->type, 'bankSerialNo' => $this->transaction_id ? $this->transaction_id : '', 'date' => $this->date ? $this->date : '', 'orderNo' => $this->out_trade_no ? $this->out_trade_no : '', 'operatorNo' => $this->operator_no ? $this->operator_no : '');
		return $reqData;
	}
}

?>
