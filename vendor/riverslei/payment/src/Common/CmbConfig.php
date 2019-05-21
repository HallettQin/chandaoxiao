<?php
//cgxlm
namespace Payment\Common;

class CmbConfig extends ConfigInterface
{
	const MAX_EXPIRE_TIME = 30;
	const REQ_FILED_NAME = 'jsonRequestData';
	const SUCC_TAG = 'SUC0000';
	const TRADE_CODE = 'FBPK';
	const NOTICE_PAY = 'BKPAYRTN';
	const NOTICE_SIGN = 'BKQY';

	public $version = '1.0';
	public $charset = 'UTF-8';
	public $merKey;
	public $dateTime;
	public $branchNo;
	public $merchantNo;
	public $returnUrl;
	public $signNoticeUrl;
	public $opPwd;
	public $getewayUrl;
	public $rsaPubKey;

	public function __construct(array $config)
	{
		try {
			$this->initConfig($config);
		}
		catch (PayException $e) {
			throw $e;
		}
	}

	protected function initConfig(array $config)
	{
		$config = \Payment\Utils\ArrayUtil::paraFilter($config);
		if (key_exists('mer_key', $config) && !empty($config['mer_key'])) {
			$this->merKey = $config['mer_key'];
		}
		else {
			throw new PayException('Mer Key 不能为空，请前往招商一网通进行设置');
		}

		if (key_exists('op_pwd', $config) && !empty($config['op_pwd'])) {
			$this->opPwd = $config['op_pwd'];
		}
		else {
			throw new PayException('请设置操作员登陆密码');
		}

		if (key_exists('notify_url', $config) && !empty($config['notify_url'])) {
			$this->notifyUrl = trim($config['notify_url']);
		}
		else {
			throw new PayException('异步通知的url必须提供.');
		}

		if (key_exists('sign_notify_url', $config) && !empty($config['sign_notify_url'])) {
			$this->signNoticeUrl = trim($config['sign_notify_url']);
		}
		else {
			throw new PayException('签约 异步通知的url必须提供.');
		}

		if (key_exists('branch_no', $config) && !empty($config['branch_no'])) {
			$this->branchNo = trim($config['branch_no']);
		}
		else {
			throw new PayException('商户分行号必须提供，4位数字.');
		}

		if (key_exists('merchant_no', $config) && !empty($config['merchant_no'])) {
			$this->merchantNo = trim($config['merchant_no']);
		}
		else {
			throw new PayException('商户号必须提供，6位数字.');
		}

		$this->limitPay = '';
		if (key_exists('limit_pay', $config) && !empty($config['limit_pay']) && (strtoupper($config['limit_pay'][0]) === 'A')) {
			$this->limitPay = 'A';
		}

		if (key_exists('return_raw', $config)) {
			$this->returnRaw = filter_var($config['return_raw'], FILTER_VALIDATE_BOOLEAN);
		}

		if (key_exists('sign_type', $config) && in_array($config['sign_type'], array('SHA-256'))) {
			$this->signType = $config['sign_type'];
		}
		else {
			$this->signType = 'SHA-256';
		}

		if (isset($config['use_sandbox']) && ($config['use_sandbox'] === true)) {
			$this->useSandbox = true;
		}
		else {
			$this->useSandbox = false;
		}

		if (key_exists('cmb_pub_key', $config) && (file_exists($config['cmb_pub_key']) || !empty($config['cmb_pub_key']))) {
			$this->rsaPubKey = \Payment\Utils\StrUtil::getRsaKeyValue($config['cmb_pub_key'], 'public');
		}
		else {
			throw new PayException('请提供招商对应的rsa公钥，可通过Helper接口获取');
		}

		if (key_exists('return_url', $config)) {
			$this->returnUrl = $config['return_url'];
		}

		$this->dateTime = date('YmdHis', time());
	}
}

?>
