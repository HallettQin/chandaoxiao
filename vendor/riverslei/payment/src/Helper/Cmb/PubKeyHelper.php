<?php
//cgxlm
namespace Payment\Helper\Cmb;

class PubKeyHelper extends \Payment\Common\Cmb\CmbBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->getewayUrl = 'https://b2b.cmbchina.com/CmbBank_B2B/UI/NetPay/DoBusiness.ashx';

		if ($this->config->useSandbox) {
			$this->config->getewayUrl = 'http://121.15.180.72/CmbBank_B2B/UI/NetPay/DoBusiness.ashx';
		}

		return 'Payment\\Common\\Cmb\\Data\\PubKeyData';
	}

	protected function retData(array $ret)
	{
		$json = json_encode($ret, JSON_UNESCAPED_UNICODE);
		$postData = \Payment\Common\CmbConfig::REQ_FILED_NAME . '=' . $json;
		$retData = $this->sendReq($postData);

		if ($this->config->returnRaw) {
			$retData['channel'] = \Payment\Config::CMB_PUB_KEY;
			return $retData;
		}

		$rData = array(
			'is_success' => 'T',
			'response'   => array('pub_key' => $retData['fbPubKey'], 'channel' => \Payment\Config::CMB_PUB_KEY, 'time' => date('Y-m-d H:i:s', strtotime($retData['dateTime'])))
			);
		return $rData;
	}
}

?>
