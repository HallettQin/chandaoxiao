<?php
//cgxlm
namespace Payment\Trans;

class WxTransfer extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		return 'Payment\\Common\\Weixin\\Data\\TransferData';
	}

	protected function getReqUrl()
	{
		return \Payment\Common\WxConfig::TRANSFERS_URL;
	}

	protected function curlPost($xml, $url)
	{
		$curl = new \Payment\Utils\Curl();
		$responseTxt = $curl->set(array('CURLOPT_HEADER' => 0, 'CURLOPT_SSL_VERIFYHOST' => false, 'CURLOPT_SSLCERTTYPE' => 'PEM', 'CURLOPT_SSLCERT' => $this->config->appCertPem, 'CURLOPT_SSLKEY' => $this->config->appKeyPem, 'CURLOPT_CAINFO' => $this->config->cacertPath))->post($xml)->submit($url);
		return $responseTxt;
	}

	protected function retData(array $ret)
	{
		if ($this->config->returnRaw) {
			$ret['channel'] = \Payment\Config::WX_TRANSFER;
			return $ret;
		}

		if ($ret['return_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $ret['return_msg'], 'channel' => \Payment\Config::WX_TRANSFER);
		}

		if ($ret['result_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $ret['err_code_des'], 'channel' => \Payment\Config::WX_TRANSFER);
		}

		return $this->createBackData($ret);
	}

	protected function createBackData(array $data)
	{
		$retData = array(
			'is_success' => 'T',
			'response'   => array('trans_no' => $data['partner_trade_no'], 'transaction_id' => $data['payment_no'], 'pay_date' => $data['payment_time'], 'device_info' => $data['device_info'], 'channel' => \Payment\Config::WX_TRANSFER)
			);
		return $retData;
	}

	protected function verifySign(array $retData)
	{
		return true;
	}
}

?>
