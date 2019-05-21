<?php
//cgxlm
namespace Payment\Client;

class Refund
{
	static private $supportChannel = array(\Payment\Config::ALI_REFUND, \Payment\Config::WX_REFUND, \Payment\Config::CMB_REFUND, 'applepay_upacp');
	/**
     * 异步通知类
     * @var RefundContext
     */
	static protected $instance;

	static protected function getInstance($channel, $config)
	{
		if (is_null(self::$instance)) {
			static::$instance = new \Payment\RefundContext();

			try {
				static::$instance->initRefund($channel, $config);
			}
			catch (\Payment\Common\PayException $e) {
				throw $e;
			}
		}

		return static::$instance;
	}

	static public function run($channel, $config, $refundData)
	{
		if (!in_array($channel, self::$supportChannel)) {
			throw new \Payment\Common\PayException('sdk当前不支持该退款渠道，当前仅支持：' . implode(',', self::$supportChannel));
		}

		try {
			$instance = self::getInstance($channel, $config);
			$ret = $instance->refund($refundData);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $ret;
	}
}


?>
