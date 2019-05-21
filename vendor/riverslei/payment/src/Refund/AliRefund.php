<?php
//cgxlm
namespace Payment\Refund;

class AliRefund extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::TRADE_REFUND_METHOD;
		return 'Payment\\Common\\Ali\\Data\\RefundData';
	}

	protected function retData(array $data)
	{
		$url = parent::retData($data);

		try {
			$rsqData = $this->sendReq($url);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		$content = json_decode($data['biz_content'], true);
		$refundNo = $content['out_request_no'];

		if ($this->config->returnRaw) {
			$rsqData['channel'] = \Payment\Config::ALI_REFUND;
			return $rsqData;
		}

		if ($rsqData['code'] !== '10000') {
			return $retData = array('is_success' => 'F', 'error' => $rsqData['sub_msg']);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('transaction_id' => $rsqData['trade_no'], 'order_no' => $rsqData['out_trade_no'], 'logon_id' => $rsqData['buyer_logon_id'], 'buyer_id' => $rsqData['buyer_user_id'], 'refund_no' => $refundNo, 'fund_change' => $rsqData['fund_change'], 'refund_fee' => $rsqData['refund_fee'], 'refund_time' => $rsqData['gmt_refund_pay'], 'channel' => \Payment\Config::ALI_REFUND)
			);
		return $retData;
	}
}

?>
