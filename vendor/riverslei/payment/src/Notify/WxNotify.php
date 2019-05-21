<?php
//cgxlm
namespace Payment\Notify;

class WxNotify extends NotifyStrategy
{
	public function __construct(array $config)
	{
		parent::__construct($config);

		try {
			$this->config = new \Payment\Common\WxConfig($config);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}
	}

	public function getNotifyData()
	{
		$data = @file_get_contents('php://input');
		$arrData = \Payment\Utils\DataParser::toArray($data);

		if (empty($arrData)) {
			return false;
		}

		$arrData = \Payment\Utils\ArrayUtil::paraFilter($arrData);
		return $arrData;
	}

	public function checkNotifyData(array $data)
	{
		if (($data['return_code'] != 'SUCCESS') || ($data['result_code'] != 'SUCCESS')) {
			return false;
		}

		return $this->verifySign($data);
	}

	protected function verifySign(array $retData)
	{
		$retSign = $retData['sign'];
		$values = \Payment\Utils\ArrayUtil::removeKeys($retData, array('sign', 'sign_type'));
		$values = \Payment\Utils\ArrayUtil::paraFilter($values);
		$values = \Payment\Utils\ArrayUtil::arraySort($values);
		$signStr = \Payment\Utils\ArrayUtil::createLinkstring($values);
		$signStr .= '&key=' . $this->config->md5Key;

		switch ($this->config->signType) {
		case 'MD5':
			$sign = md5($signStr);
			break;

		case 'HMAC-SHA256':
			$sign = hash_hmac('sha256', $signStr, $this->config->md5Key);
			break;

		default:
			$sign = '';
		}

		return strtoupper($sign) === $retSign;
	}

	protected function getRetData(array $data)
	{
		if ($this->config->returnRaw) {
			$data['channel'] = \Payment\Config::WX_CHARGE;
			return $data;
		}

		$totalFee = bcdiv($data['total_fee'], 100, 2);
		$cashFee = bcdiv($data['cash_fee'], 100, 2);
		$retData = array('bank_type' => $data['bank_type'], 'cash_fee' => $cashFee, 'device_info' => $data['device_info'], 'fee_type' => $data['fee_type'], 'is_subscribe' => $data['is_subscribe'], 'buyer_id' => $data['openid'], 'order_no' => $data['out_trade_no'], 'pay_time' => date('Y-m-d H:i:s', strtotime($data['time_end'])), 'amount' => $totalFee, 'trade_type' => $data['trade_type'], 'transaction_id' => $data['transaction_id'], 'trade_state' => strtolower($data['return_code']), 'channel' => \Payment\Config::WX_CHARGE);
		if (isset($data['attach']) && !empty($data['attach'])) {
			$retData['return_param'] = $data['attach'];
		}

		return $retData;
	}

	protected function replyNotify($flag, $msg = 'OK')
	{
		$result = array('return_code' => 'SUCCESS', 'return_msg' => 'OK');

		if (!$flag) {
			$result = array('return_code' => 'FAIL', 'return_msg' => $msg);
		}

		return \Payment\Utils\DataParser::toXml($result);
	}
}

?>
