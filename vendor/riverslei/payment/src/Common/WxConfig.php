<?php
//cgxlm
namespace Payment\Common;

final class WxConfig extends ConfigInterface
{
	const UNIFIED_URL = 'https://api.mch.weixin.qq.com/{debug}/pay/unifiedorder';
	const MICROPAY_URL = 'https://api.mch.weixin.qq.com/{debug}/pay/micropay';
	const CHARGE_QUERY_URL = 'https://api.mch.weixin.qq.com/{debug}/pay/orderquery';
	const REFUDN_QUERY_URL = 'https://api.mch.weixin.qq.com/{debug}/pay/refundquery';
	const TRANS_QUERY_URL = 'https://api.mch.weixin.qq.com/{debug}/mmpaymkttransfers/gettransferinfo';
	const REFUND_URL = 'https://api.mch.weixin.qq.com/{debug}/secapi/pay/refund';
	const TRANSFERS_URL = 'https://api.mch.weixin.qq.com/{debug}/mmpaymkttransfers/promotion/transfers';
	const CLOSE_URL = 'https://api.mch.weixin.qq.com/{debug}/pay/closeorder';
	const SHORT_URL = 'https://api.mch.weixin.qq.com/{debug}/tools/shorturl';
	const REFUND_UNSETTLED = 'REFUND_SOURCE_UNSETTLED_FUNDS';
	const REFUND_RECHARGE = 'REFUND_SOURCE_RECHARGE_FUNDS';
	const SANDBOX_PRE = 'sandboxnew';
	const SANDBOX_URL = 'https://api.mch.weixin.qq.com/sandboxnew/pay/getsignkey';

	public $appId;
	public $mchId;
	public $nonceStr;
	public $feeType = 'CNY';
	public $timeStart;
	public $md5Key;
	public $cacertPath;
	public $appCertPem;
	public $appKeyPem;
	public $tradeType;
	public $returnUrl;

	public function __construct(array $config)
	{
		try {
			$this->initConfig($config);
		}
		catch (PayException $e) {
			throw $e;
		}

		$basePath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'CacertFile' . DIRECTORY_SEPARATOR;
		$this->cacertPath = $basePath . 'wx_cacert.pem';
	}

	private function initConfig(array $config)
	{
		$config = \Payment\Utils\ArrayUtil::paraFilter($config);
		if (key_exists('app_id', $config) && !empty($config['app_id'])) {
			$this->appId = $config['app_id'];
		}
		else {
			throw new PayException('必须提供微信分配的公众账号ID');
		}

		if (key_exists('mch_id', $config) && !empty($config['mch_id'])) {
			$this->mchId = $config['mch_id'];
		}
		else {
			throw new PayException('必须提供微信支付分配的商户号');
		}

		if (key_exists('notify_url', $config) && !empty($config['notify_url'])) {
			$this->notifyUrl = trim($config['notify_url']);
		}
		else {
			throw new PayException('异步通知的url必须提供.');
		}

		$startTime = time();
		$this->timeStart = date('YmdHis', $startTime);
		if (key_exists('md5_key', $config) && !empty($config['md5_key'])) {
			$this->md5Key = $config['md5_key'];
		}
		else {
			throw new PayException('MD5 Key 不能为空，再微信商户后台可查看');
		}

		if (key_exists('fee_type', $config) && in_array($config['fee_type'], array('CNY'))) {
			$this->feeType = $config['fee_type'];
		}

		if (key_exists('limit_pay', $config) && !empty($config['limit_pay']) && ($config['limit_pay'][0] === 'no_credit')) {
			$this->limitPay = $config['limit_pay'][0];
		}

		if (key_exists('return_raw', $config)) {
			$this->returnRaw = filter_var($config['return_raw'], FILTER_VALIDATE_BOOLEAN);
		}

		if (key_exists('redirect_url', $config)) {
			$this->returnUrl = $config['redirect_url'];
		}

		if (!empty($config['app_cert_pem'])) {
			$this->appCertPem = $config['app_cert_pem'];
		}

		if (!empty($config['app_key_pem'])) {
			$this->appKeyPem = $config['app_key_pem'];
		}

		if (key_exists('sign_type', $config) && in_array($config['sign_type'], array('MD5', 'HMAC-SHA256'))) {
			$this->signType = $config['sign_type'];
		}
		else {
			$this->signType = 'MD5';
		}

		$this->nonceStr = \Payment\Utils\StrUtil::getNonceStr();
		if (isset($config['use_sandbox']) && ($config['use_sandbox'] === true)) {
			$this->useSandbox = true;
			$helper = new Weixin\WechatHelper($this, array());
			$this->md5Key = $helper->getSandboxSignKey();
		}
		else {
			$this->useSandbox = false;
		}
	}
}

?>
