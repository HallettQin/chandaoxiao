<?php
//cgxlm
namespace Payment\Query\Ali;

class AliRefundQuery extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::REFUND_QUERY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Query\\RefundQueryData';
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
			$ret['channel'] = \Payment\Config::ALI_REFUND;
			return $ret;
		}

		return $this->createBackData($ret);
	}

	protected function createBackData(array $data)
	{
		if ($data['code'] !== '10000') {
			return $retData = array('is_success' => 'F', 'error' => $data['sub_msg'], 'channel' => \Payment\Config::ALI_REFUND);
		}

		if (empty($data['out_trade_no'])) {
			return array('is_success' => 'T', 'msg' => strtolower($data['msg']), 'channel' => \Payment\Config::ALI_REFUND);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('amount' => $data['total_amount'], 'refund_amount' => $data['refund_amount'], 'order_no' => $data['out_trade_no'], 'refund_no' => $data['out_request_no'], 'transaction_id' => $data['trade_no'], 'reason' => $data['refund_reason'], 'channel' => \Payment\Config::ALI_REFUND)
			);
		return $retData;
	}
}

?>
