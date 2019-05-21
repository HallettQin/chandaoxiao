<?php
//cgxlm
namespace Payment\Refund;

class WxRefund extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		return 'Payment\\Common\\Weixin\\Data\\RefundData';
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
			$ret['channel'] = \Payment\Config::WX_REFUND;
			return $ret;
		}

		if ($ret['return_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $ret['return_msg']);
		}

		if ($ret['result_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $ret['err_code_des']);
		}

		return $this->createBackData($ret);
	}

	protected function createBackData(array $data)
	{
		$total_fee = bcdiv($data['total_fee'], 100, 2);
		$refund_fee = bcdiv($data['refund_fee'], 100, 2);
		$retData = array(
			'is_success' => 'T',
			'response'   => array('transaction_id' => $data['transaction_id'], 'order_no' => $data['out_trade_no'], 'refund_no' => $data['out_refund_no'], 'refund_id' => $data['refund_id'], 'refund_fee' => $refund_fee, 'refund_channel' => $data['refund_channel'], 'amount' => $total_fee, 'channel' => \Payment\Config::WX_REFUND, 'coupon_refund_fee' => bcdiv($data['coupon_refund_fee'], 100, 2), 'coupon_refund_count' => $data['coupon_refund_count'], 'cash_fee' => bcdiv($data['cash_fee'], 100, 2), 'cash_refund_fee' => bcdiv($data['cash_refund_fee'], 100, 2))
			);
		return $retData;
	}

	protected function getReqUrl()
	{
		return \Payment\Common\WxConfig::REFUND_URL;
	}
}

?>
