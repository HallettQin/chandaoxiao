<?php
//cgxlm
namespace Payment\Notify;

class AliNotify extends NotifyStrategy
{
	public function __construct(array $config)
	{
		parent::__construct($config);

		try {
			$this->config = new \Payment\Common\AliConfig($config);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}
	}

	protected function getOldAliPublicKey()
	{
		$filePath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'CacertFile/old_alipay_public_key.pem';
		return @file_get_contents($filePath);
	}

	public function getNotifyData()
	{
		$data = (empty($_POST) ? $_GET : $_POST);
		if (empty($data) || !is_array($data)) {
			return false;
		}

		return $data;
	}

	public function checkNotifyData(array $data)
	{
		$status = $this->getTradeStatus($data['trade_status']);

		if ($status !== \Payment\Config::TRADE_STATUS_SUCC) {
			return false;
		}

		if (!isset($data['version'])) {
			$this->config->rsaAliPubKey = $this->getOldAliPublicKey();
		}

		$flag = $this->verifySign($data);
		return $flag;
	}

	protected function getRetData(array $data)
	{
		if ($this->config->returnRaw) {
			$data['channel'] = \Payment\Config::ALI_CHARGE;
			return $data;
		}

		if (!isset($data['version'])) {
			$retData = array('order_no' => $data['out_trade_no'], 'subject' => $data['subject'], 'transaction_id' => $data['trade_no'], 'trade_state' => $this->getTradeStatus($data['trade_status']), 'trade_create_time' => $data['gmt_create'], 'pay_time' => $data['gmt_payment'], 'seller_id' => $data['seller_id'], 'seller_email' => $data['seller_email'], 'buyer_id' => $data['buyer_id'], 'amount' => $data['total_fee'], 'channel' => \Payment\Config::ALI_CHARGE, 'body' => $data['body'], 'discount' => $data['discount'], 'return_param' => $data['extra_common_param'], 'notify_time' => $data['notify_time'], 'notify_type' => $data['notify_type']);
		}
		else {
			$retData = array('amount' => $data['total_amount'], 'buyer_id' => $data['buyer_id'], 'transaction_id' => $data['trade_no'], 'body' => $data['body'], 'notify_time' => $data['notify_time'], 'subject' => $data['subject'], 'buyer_account' => $data['buyer_logon_id'], 'auth_app_id' => $data['auth_app_id'], 'notify_type' => $data['notify_type'], 'invoice_amount' => $data['invoice_amount'], 'order_no' => $data['out_trade_no'], 'trade_state' => $this->getTradeStatus($data['trade_status']), 'pay_time' => $data['gmt_payment'], 'point_amount' => $data['point_amount'], 'trade_create_time' => $data['gmt_create'], 'pay_amount' => $data['buyer_pay_amount'], 'receipt_amount' => $data['receipt_amount'], 'fund_bill_list' => $data['fund_bill_list'], 'app_id' => $data['app_id'], 'seller_id' => $data['seller_id'], 'seller_email' => $data['seller_email'], 'channel' => \Payment\Config::ALI_CHARGE);
		}

		if (isset($data['passback_params']) && !empty($data['passback_params'])) {
			$retData['return_param'] = $data['passback_params'];
		}

		return $retData;
	}

	protected function replyNotify($flag, $msg = '')
	{
		if ($flag) {
			return 'success';
		}
		else {
			return 'fail';
		}
	}

	protected function getTradeStatus($status)
	{
		if (in_array($status, array('TRADE_SUCCESS', 'TRADE_FINISHED'))) {
			return \Payment\Config::TRADE_STATUS_SUCC;
		}
		else {
			return \Payment\Config::TRADE_STATUS_FAILD;
		}
	}

	protected function verifySign(array $data)
	{
		$signType = strtoupper($data['sign_type']);
		$sign = $data['sign'];
		$values = \Payment\Utils\ArrayUtil::removeKeys($data, array('sign', 'sign_type'));
		$values = \Payment\Utils\ArrayUtil::paraFilter($values);
		$values = \Payment\Utils\ArrayUtil::arraySort($values);
		$preStr = \Payment\Utils\ArrayUtil::createLinkstring($values);

		if ($signType === 'RSA') {
			$rsa = new \Payment\Utils\RsaEncrypt($this->config->rsaAliPubKey);
			return $rsa->rsaVerify($preStr, $sign);
		}
		else if ($signType === 'RSA2') {
			$rsa = new \Payment\Utils\Rsa2Encrypt($this->config->rsaAliPubKey);
			return $rsa->rsaVerify($preStr, $sign);
		}
		else {
			return false;
		}
	}
}

?>
