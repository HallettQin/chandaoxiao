<?php
//cgxlm
namespace Payment\Refund;

class CmbRefund extends \Payment\Common\Cmb\CmbBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->getewayUrl = 'https://payment.ebank.cmbchina.com/NetPayment/BaseHttp.dll?DoRefund';

		if ($this->config->useSandbox) {
			$this->config->getewayUrl = 'http://121.15.180.66:801/NetPayment_dl/BaseHttp.dll?DoRefund';
		}

		return 'Payment\\Common\\Cmb\\Data\\RefundData';
	}

	protected function retData(array $ret)
	{
		$json = json_encode($ret, JSON_UNESCAPED_UNICODE);
		$postData = \Payment\Common\CmbConfig::REQ_FILED_NAME . '=' . $json;
		$rsqData = $this->sendReq($postData);

		if ($this->config->returnRaw) {
			$rsqData['channel'] = \Payment\Config::CMB_REFUND;
			return $rsqData;
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('transaction_id' => $rsqData['bankSerialNo'], 'order_no' => $ret['reqData']['orderNo'], 'date' => $ret['reqData']['date'], 'refund_no' => trim($rsqData['refundSerialNo']), 'refund_id' => $rsqData['refundRefNo'], 'currency' => $rsqData['currency'], 'refund_fee' => $rsqData['amount'], 'channel' => \Payment\Config::CMB_REFUND, 'refund_time' => date('Y-m-d H:i:s', strtotime($rsqData['bankDate'] . $rsqData['bankTime'])))
			);
		return $retData;
	}
}

?>
