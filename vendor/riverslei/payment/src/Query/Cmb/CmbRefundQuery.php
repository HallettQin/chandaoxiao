<?php
//cgxlm
namespace Payment\Query\Cmb;

class CmbRefundQuery extends \Payment\Common\Cmb\CmbBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->getewayUrl = 'https://payment.ebank.cmbchina.com/NetPayment/BaseHttp.dll?QuerySettledRefund';

		if ($this->config->useSandbox) {
			$this->config->getewayUrl = 'http://121.15.180.66:801/netpayment_dl/BaseHttp.dll?QuerySettledRefund';
		}

		return 'Payment\\Common\\Cmb\\Data\\Query\\RefundQueryData';
	}

	protected function retData(array $ret)
	{
		$json = json_encode($ret, JSON_UNESCAPED_UNICODE);
		$postData = \Payment\Common\CmbConfig::REQ_FILED_NAME . '=' . $json;
		$retData = $this->sendReq($postData);

		if ($this->config->returnRaw) {
			$retData['channel'] = \Payment\Config::CMB_REFUND;
			return $retData;
		}

		$list = $retData['dataList'];
		$list = str_replace('`', '', $list);
		$list = explode(PHP_EOL, $list);
		$header = array_shift($list);
		$header = explode(',', $header);

		foreach ($list as $key => $item) {
			$item = explode(',', $item);
			$list[$key] = array_combine($header, $item);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('channel' => \Payment\Config::CMB_REFUND, 'refund_data' => $list)
			);
		return $retData;
	}
}

?>
