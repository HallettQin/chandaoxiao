<?php
//cgxlm
namespace Payment\Common\Ali\Data\Query;

class TransferQueryData extends QueryBaseData
{
	protected function getBizContent()
	{
		$content = array('out_biz_no' => $this->trans_no, 'order_id' => $this->transaction_id);
		$content = \Payment\Utils\ArrayUtil::paraFilter($content);
		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}

	protected function checkDataParam()
	{
		$transNo = $this->trans_no;
		$transactionId = $this->transaction_id;
		if (empty($transactionId) && empty($transNo)) {
			throw new \Payment\Common\PayException('必须提供支付宝转账单据号或者商户转账单号');
		}
	}
}

?>
