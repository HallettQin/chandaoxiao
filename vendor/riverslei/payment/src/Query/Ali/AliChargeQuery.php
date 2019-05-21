<?php
//cgxlm
namespace Payment\Query\Ali;

class AliChargeQuery extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::TRADE_QUERY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Query\\ChargeQueryData';
	}

	protected function retData(array $data)
	{
		$url = parent::retData($data);

		try {
			$ret = $this->sendReq($url);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		if ($this->config->returnRaw) {
			$ret['channel'] = \Payment\Config::ALI_CHARGE;
			return $ret;
		}

		return $this->createBackData($ret);
	}

	protected function createBackData(array $data)
	{
		if ($data['code'] !== '10000') {
			return $retData = array('is_success' => 'F', 'error' => $data['sub_msg']);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('amount' => $data['total_amount'], 'channel' => \Payment\Config::ALI_CHARGE, 'order_no' => $data['out_trade_no'], 'buyer_id' => $data['buyer_user_id'], 'trade_state' => $this->getTradeStatus($data['trade_status']), 'transaction_id' => $data['trade_no'], 'time_end' => $data['send_pay_date'], 'receipt_amount' => $data['receipt_amount'], 'pay_amount' => $data['buyer_pay_amount'], 'point_amount' => $data['point_amount'], 'invoice_amount' => $data['invoice_amount'], 'fund_bill_list' => empty($data['fund_bill_list']) ? '' : $data['fund_bill_list'], 'logon_id' => $data['buyer_logon_id'])
			);
		return $retData;
	}
}

?>
