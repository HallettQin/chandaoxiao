<?php
//cgxlm
namespace Payment\Client;

class Charge
{
	static private $supportChannel = array(\Payment\Config::ALI_CHANNEL_APP, \Payment\Config::ALI_CHANNEL_WAP, \Payment\Config::ALI_CHANNEL_WEB, \Payment\Config::ALI_CHANNEL_QR, \Payment\Config::ALI_CHANNEL_BAR, \Payment\Config::WX_CHANNEL_APP, \Payment\Config::WX_CHANNEL_PUB, \Payment\Config::WX_CHANNEL_QR, \Payment\Config::WX_CHANNEL_BAR, \Payment\Config::WX_CHANNEL_WAP, \Payment\Config::WX_CHANNEL_LITE, \Payment\Config::CMB_CHANNEL_APP, 'applepay_upacp');
	/**
     * 异步通知类
     * @var ChargeContext
     */
	static protected $instance;

	static protected function getInstance($channel, $config)
	{
		if (is_null(self::$instance)) {
			static::$instance = new \Payment\ChargeContext();

			try {
				static::$instance->initCharge($channel, $config);
			}
			catch (\Payment\Common\PayException $e) {
				throw $e;
			}
		}

		return static::$instance;
	}

	static public function run($channel, $config, $metadata)
	{
		if (!in_array($channel, self::$supportChannel)) {
			throw new \Payment\Common\PayException('sdk当前不支持该支付渠道，当前仅支持：' . implode(',', self::$supportChannel));
		}

		try {
			$instance = self::getInstance($channel, $config);
			$ret = $instance->charge($metadata);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $ret;
	}
}


?>
