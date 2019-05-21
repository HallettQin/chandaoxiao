<?php
//cgxlm
namespace Payment\Client;

class Helper
{
	static private $supportChannel = array(\Payment\Config::CMB_BIND, \Payment\Config::CMB_PUB_KEY);
	/**
     * 异步通知类
     * @var HelperContext
     */
	static protected $instance;

	static protected function getInstance($channel, $config)
	{
		if (is_null(self::$instance)) {
			static::$instance = new \Payment\HelperContext();

			try {
				static::$instance->initHelper($channel, $config);
			}
			catch (\Payment\Common\PayException $e) {
				throw $e;
			}
		}

		return static::$instance;
	}

	static public function run($channel, $config, array $metadata = array())
	{
		if (!in_array($channel, self::$supportChannel)) {
			throw new \Payment\Common\PayException('sdk当前不支持该渠道，当前仅支持：' . implode(',', self::$supportChannel));
		}

		try {
			$instance = self::getInstance($channel, $config);
			$ret = $instance->helper($metadata);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $ret;
	}
}


?>
