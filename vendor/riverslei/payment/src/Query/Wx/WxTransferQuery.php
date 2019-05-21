<?php
//cgxlm
namespace Payment\Query\Wx;

class WxTransferQuery extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		return 'Payment\\Common\\Weixin\\Data\\Query\\TransferQueryData';
	}

	protected function curlPost($xml, $url)
	{
		$curl = new \Payment\Utils\Curl();
		$responseTxt = $curl->set(array('CURLOPT_HEADER' => 0, 'CURLOPT_SSL_VERIFYHOST' => false, 'CURLOPT_SSLCERTTYPE' => 'PEM', 'CURLOPT_SSLCERT' => $this->config->appCertPem, 'CURLOPT_SSLKEY' => $this->config->appKeyPem, 'CURLOPT_CAINFO' => $this->config->cacertPath))->post($xml)->submit($url);
		return $responseTxt;
	}

	protected function getReqUrl()
	{
		return \Payment\Common\WxConfig::TRANS_QUERY_URL;
	}

	protected function retData(array $data)
	{
		if ($this->config->returnRaw) {
			$data['channel'] = \Payment\Config::WX_TRANSFER;
			return $data;
		}

		if ($data['return_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $data['return_msg'], 'channel' => \Payment\Config::WX_TRANSFER);
		}

		if ($data['result_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $data['err_code_des'], 'channel' => \Payment\Config::WX_TRANSFER);
		}

		return $this->createBackData($data);
	}

	protected function createBackData(array $data)
	{
		$amount = bcdiv($data['payment_amount'], 100, 2);
		$retData = array(
			'is_success' => 'T',
			'response'   => array('trans_no' => $data['partner_trade_no'], 'transaction_id' => $data['detail_id'], 'status' => strtolower($data['status']), 'reason' => $data['reason'], 'openid' => $data['openid'], 'payee_name' => $data['transfer_name'], 'amount' => $amount, 'pay_date' => $data['transfer_time'], 'desc' => $data['desc'], 'channel' => \Payment\Config::WX_TRANSFER)
			);
		return $retData;
	}

	public function handle(array $data)
	{
		$buildClass = $this->getBuildDataClass();

		try {
			$this->reqData = new $buildClass($this->config, $data);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		$this->reqData->setSign();
		$xml = \Payment\Utils\DataParser::toXml($this->reqData->getData());
		$ret = $this->sendReq($xml);
		return $this->retData($ret);
	}
}

?>
