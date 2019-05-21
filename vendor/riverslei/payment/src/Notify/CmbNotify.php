<?php
//cgxlm
namespace Payment\Notify;

class CmbNotify extends NotifyStrategy
{
	public function __construct(array $config)
	{
		parent::__construct($config);

		try {
			$this->config = new \Payment\Common\CmbConfig($config);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}
	}

	public function getNotifyData()
	{
		$data = (empty($_POST) ? $_GET : $_POST);
		if (empty($data) || !is_array($data)) {
			return false;
		}

		$retData = json_decode($data[\Payment\Common\CmbConfig::REQ_FILED_NAME], true);
		return $retData;
	}

	public function checkNotifyData(array $data)
	{
		$signType = strtoupper($data['signType']);
		$sign = $data['sign'];
		$values = \Payment\Utils\ArrayUtil::arraySort($data['noticeData']);
		$preStr = \Payment\Utils\ArrayUtil::createLinkstring($values);

		if ($signType === 'RSA') {
			$rsa = new \Payment\Utils\RsaEncrypt($this->config->rsaPubKey);
			return $rsa->rsaVerify($preStr, $sign);
		}
		else {
			return false;
		}
	}

	protected function getRetData(array $data)
	{
		$noticeData = $data['noticeData'];
		$noticeType = $noticeData['noticeType'];

		if ($noticeType === \Payment\Common\CmbConfig::NOTICE_PAY) {
			$channel = \Payment\Config::CMB_CHARGE;
		}
		else if ($noticeType === \Payment\Common\CmbConfig::NOTICE_SIGN) {
			$channel = \Payment\Config::CMB_BIND;
		}
		else {
			$channel = 'other';
		}

		if (!$this->config->returnRaw) {
			$data['channel'] = $channel;
			return $data;
		}
		else if ($noticeType === \Payment\Common\CmbConfig::NOTICE_PAY) {
			$retData = array('amount' => $noticeData['amount'], 'channel' => $channel, 'date' => $noticeData['date'], 'order_no' => $noticeData['orderNo'], 'trade_state' => \Payment\Config::TRADE_STATUS_SUCC, 'transaction_id' => $noticeData['bankSerialNo'], 'time_end' => date('Y-m-d H:i:s', strtotime($noticeData['dateTime'])), 'discount_fee' => $noticeData['discountAmount'], 'card_type' => $noticeData['cardType'], 'return_param' => $noticeData['merchantPara'], 'discount_flag' => $noticeData['discountFlag'], 'notice_no' => $noticeData['noticeSerialNo']);
		}
		else if ($noticeType === \Payment\Common\CmbConfig::NOTICE_SIGN) {
			$retData = array('user_id' => $noticeData['userID'], 'no_pwd_pay' => $noticeData['noPwdPay'], 'notice_no' => $noticeData['noticeSerialNo'], 'agr_no' => $noticeData['agrNo'], 'rsp_msg' => $noticeData['rspMsg'], 'return_param' => $noticeData['noticePara'], 'user_pid_hash' => $noticeData['userPidHash'], 'user_pid_type' => $noticeData['userPidType'], 'channel' => $channel);
		}
		else {
			$retData = $noticeData;
		}

		return $retData;
	}

	protected function replyNotify($flag, $msg = 'OK')
	{
		if ($flag) {
			header('HTTP/1.1 200 OK');
			return $msg;
		}
		else {
			header('HTTP/1.1 503 Service Unavailable');
			return $msg;
		}
	}
}

?>
