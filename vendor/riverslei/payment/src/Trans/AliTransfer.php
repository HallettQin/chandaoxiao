<?php
//cgxlm
namespace Payment\Trans;

class AliTransfer extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::TRANS_TOACCOUNT_METHOD;
		return 'Payment\\Common\\Ali\\Data\\TransData';
	}

	protected function retData(array $data)
	{
		$url = parent::retData($data);

		try {
			$data = $this->sendReq($url);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		if ($this->config->returnRaw) {
			$data['channel'] = \Payment\Config::ALI_TRANSFER;
			return $data;
		}

		return $this->createBackData($data);
	}

	protected function createBackData(array $data)
	{
		if ($data['code'] !== '10000') {
			return $retData = array('is_success' => 'F', 'error' => $data['sub_msg'], 'channel' => \Payment\Config::ALI_TRANSFER);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('transaction_id' => $data['order_id'], 'trans_no' => $data['out_biz_no'], 'pay_date' => $data['pay_date'], 'channel' => \Payment\Config::ALI_TRANSFER)
			);
		return $retData;
	}
}

?>
