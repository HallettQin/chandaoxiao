<?php
//cgxlm
namespace Payment\Query\Ali;

class AliTransferQuery extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::TRANS_QUERY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Query\\TransferQueryData';
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
			$ret['channel'] = \Payment\Config::ALI_TRANSFER;
			return $ret;
		}

		return $this->createBackData($ret);
	}

	protected function createBackData(array $data)
	{
		if ($data['code'] !== '10000') {
			return $retData = array('is_success' => 'F', 'error' => $data['sub_msg'], 'channel' => \Payment\Config::ALI_TRANSFER);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('transaction_id' => $data['order_id'], 'amount' => $data['order_fee'], 'trans_no' => $data['out_biz_no'], 'pay_date' => $data['pay_date'], 'status' => strtolower($data['status']), 'fail_reason' => $data['fail_reason'], 'arrival_time_end' => $data['arrival_time_end'], 'channel' => \Payment\Config::ALI_TRANSFER)
			);
		return $retData;
	}
}

?>
