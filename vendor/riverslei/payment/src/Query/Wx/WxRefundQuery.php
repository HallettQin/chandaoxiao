<?php
//cgxlm
namespace Payment\Query\Wx;

class WxRefundQuery extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		return 'Payment\\Common\\Weixin\\Data\\Query\\RefundQueryData';
	}

	protected function getReqUrl()
	{
		return \Payment\Common\WxConfig::REFUDN_QUERY_URL;
	}

	protected function retData(array $data)
	{
		if ($this->config->returnRaw) {
			$data['channel'] = \Payment\Config::WX_REFUND;
			return $data;
		}

		if ($data['return_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $data['return_msg'], 'channel' => \Payment\Config::WX_REFUND);
		}

		if ($data['result_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $data['err_code_des'], 'channel' => \Payment\Config::WX_REFUND);
		}

		return $this->createBackData($data);
	}

	protected function createBackData(array $data)
	{
		$refund_count = $data['refund_count'];
		$totalFee = bcdiv($data['total_fee'], 100, 2);
		$refundFee = bcdiv($data['refund_fee'], 100, 2);
		$refundData = array();

		for ($i = 0; $i < $refund_count; $i++) {
			$refund_no = 'out_refund_no_' . $i;
			$refund_id = 'refund_id_' . $i;
			$refund_channel = 'refund_channel_' . $i;
			$refund_fee = 'refund_fee_' . $i;
			$refund_status = 'refund_status_' . $i;
			$recv_accout = 'refund_recv_accout_' . $i;
			$fee = bcdiv($refund_fee, 100, 2);
			$refundData[] = array('refund_no' => $data[$refund_no], 'refund_id' => $data[$refund_id], 'refund_channel' => $data[$refund_channel], 'refund_fee' => $fee, 'refund_status' => strtolower($data[$refund_status]), 'recv_accout' => $data[$recv_accout]);
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('amount' => $totalFee, 'order_no' => $data['out_trade_no'], 'transaction_id' => $data['transaction_id'], 'refund_count' => $data['refund_count'], 'refund_fee' => $refundFee, 'refund_data' => $refundData, 'channel' => \Payment\Config::WX_REFUND)
			);
		return $retData;
	}
}

?>
