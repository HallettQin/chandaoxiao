<?php
//cgxlm
namespace Payment\Query\Wx;

class WxChargeQuery extends \Payment\Common\Weixin\WxBaseStrategy
{
	public function getBuildDataClass()
	{
		return 'Payment\\Common\\Weixin\\Data\\Query\\ChargeQueryData';
	}

	protected function getReqUrl()
	{
		return \Payment\Common\WxConfig::CHARGE_QUERY_URL;
	}

	protected function retData(array $data)
	{
		if ($this->config->returnRaw) {
			$data['channel'] = \Payment\Config::WX_CHARGE;
			return $data;
		}

		if ($data['return_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $data['return_msg'], 'channel' => \Payment\Config::WX_CHARGE);
		}

		if ($data['result_code'] != 'SUCCESS') {
			return $retData = array('is_success' => 'F', 'error' => $data['err_code_des'], 'channel' => \Payment\Config::WX_CHARGE);
		}

		return $this->createBackData($data);
	}

	protected function createBackData(array $data)
	{
		$totalFee = bcdiv($data['total_fee'], 100, 2);
		$retData = array(
			'is_success' => 'T',
			'response'   => array('amount' => $totalFee, 'channel' => \Payment\Config::WX_CHARGE, 'order_no' => $data['out_trade_no'], 'buyer_id' => $data['openid'], 'trade_state' => strtolower($data['trade_state']), 'transaction_id' => $data['transaction_id'], 'time_end' => date('Y-m-d H:i:s', strtotime($data['time_end'])), 'return_param' => $data['attach'], 'terminal_id' => $data['device_info'], 'trade_type' => $data['trade_type'], 'bank_type' => $data['bank_type'], 'trade_state_desc' => isset($data['trade_state_desc']) ? $data['trade_state_desc'] : '交易成功')
			);
		return \Payment\Utils\ArrayUtil::paraFilter($retData);
	}
}

?>
