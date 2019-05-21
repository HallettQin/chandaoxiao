<?php
//cgxlm
namespace Payment\Client;

class Transfer
{
	static private $supportChannel = array(\Payment\Config::ALI_TRANSFER, \Payment\Config::WX_TRANSFER, 'cmb_wallet', 'applepay_upacp');
	/**
     * 异步通知类
     * @var TransferContext
     */
	static protected $instance;

	static protected function getInstance($channel, $config)
	{
		if (is_null(self::$instance)) {
			static::$instance = new \Payment\TransferContext();

			try {
				static::$instance->initTransfer($channel, $config);
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
			throw new \Payment\Common\PayException('sdk当前不支持该退款渠道，当前仅支持：' . implode(',', self::$supportChannel));
		}

		try {
			$instance = self::getInstance($channel, $config);
			$ret = $instance->transfer($metadata);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $ret;
	}
}


?>
