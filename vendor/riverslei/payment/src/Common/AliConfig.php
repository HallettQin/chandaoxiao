<?php
//cgxlm
namespace Payment\Common;

final class AliConfig extends ConfigInterface
{
	const WAP_PAY_METHOD = 'alipay.trade.wap.pay';
	const APP_PAY_METHOD = 'alipay.trade.app.pay';
	const PC_PAY_METHOD = 'alipay.trade.page.pay';
	const QR_PAY_METHOD = 'alipay.trade.precreate';
	const BAR_PAY_METHOD = 'alipay.trade.pay';
	const TRADE_QUERY_METHOD = 'alipay.trade.query';
	const REFUND_QUERY_METHOD = 'alipay.trade.fastpay.refund.query';
	const TRANS_QUERY_METHOD = 'alipay.fund.trans.order.query';
	const TRADE_REFUND_METHOD = 'alipay.trade.refund';
	const TRANS_TOACCOUNT_METHOD = 'alipay.fund.trans.toaccount.transfer';

	public $getewayUrl;
	public $appId;
	public $method;
	public $format = 'JSON';
	public $returnUrl;
	public $charset = 'UTF-8';
	public $timestamp;
	public $version = '1.0';
	public $partner;
	public $rsaPrivateKey;
	public $rsaAliPubKey;

	public function __construct(array $config)
	{
		try {
			$this->initConfig($config);
		}
		catch (PayException $e) {
			throw $e;
		}
	}

	private function initConfig(array $config)
	{
		$config = \Payment\Utils\ArrayUtil::paraFilter($config);
		$this->getewayUrl = 'https://openapi.alipay.com/gateway.do?';
		if (isset($config['use_sandbox']) && ($config['use_sandbox'] === true)) {
			$this->getewayUrl = 'https://openapi.alipaydev.com/gateway.do?';
		}
		else {
			$this->useSandbox = false;
		}

		if (key_exists('app_id', $config) && is_numeric($config['app_id'])) {
			$this->appId = $config['app_id'];
		}
		else {
			throw new PayException('缺少支付宝分配给开发者的应用ID，请在开发者中心查看');
		}

		if (key_exists('notify_url', $config)) {
			$this->notifyUrl = $config['notify_url'];
		}

		if (key_exists('return_url', $config)) {
			$this->returnUrl = $config['return_url'];
		}

		if (key_exists('sign_type', $config) && in_array($config['sign_type'], array('RSA', 'RSA2'))) {
			$this->signType = $config['sign_type'];
		}
		else {
			throw new PayException('目前支付宝仅支持RSA2和RSA，推荐使用RSA2');
		}

		if (key_exists('ali_public_key', $config) && (file_exists($config['ali_public_key']) || !empty($config['ali_public_key']))) {
			$this->rsaAliPubKey = \Payment\Utils\StrUtil::getRsaKeyValue($config['ali_public_key'], 'public');
		}
		else {
			throw new PayException('请提供支付宝对应的rsa公钥');
		}

		if (key_exists('rsa_private_key', $config) && (file_exists($config['rsa_private_key']) || !empty($config['ali_public_key']))) {
			$this->rsaPrivateKey = \Payment\Utils\StrUtil::getRsaKeyValue($config['rsa_private_key'], 'private');
		}
		else {
			throw new PayException('请提供商户的rsa私钥文件');
		}

		$this->timestamp = date('Y-m-d H:i:s', time());

		if (key_exists('partner', $config)) {
			$this->partner = $config['partner'];
		}

		if (key_exists('limit_pay', $config) && is_array($config['limit_pay'])) {
			$this->limitPay = implode(',', $config['limit_pay']);
		}

		if (key_exists('return_raw', $config)) {
			$this->returnRaw = filter_var($config['return_raw'], FILTER_VALIDATE_BOOLEAN);
		}
	}
}

?>
