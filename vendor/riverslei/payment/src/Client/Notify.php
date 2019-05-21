<?php
//cgxlm
namespace Payment\Client;

class Notify
{
	static private $supportChannel = array(\Payment\Config::ALI_CHARGE, \Payment\Config::WX_CHARGE, \Payment\Config::CMB_CHARGE, 'applepay_upacp');
	/**
     * 异步通知类
     * @var NotifyContext
     */
	static protected $instance;

	static protected function getInstance($type, $config)
	{
		if (is_null(self::$instance)) {
			static::$instance = new \Payment\NotifyContext();

			try {
				static::$instance->initNotify($type, $config);
			}
			catch (\Payment\Common\PayException $e) {
				throw $e;
			}
		}

		return static::$instance;
	}

	static public function run($type, $config, $callback)
	{
		if (!in_array($type, self::$supportChannel)) {
			throw new \Payment\Common\PayException('sdk当前不支持该异步方式，当前仅支持：' . implode(',', self::$supportChannel));
		}

		try {
			$instance = self::getInstance($type, $config);
			$ret = $instance->notify($callback);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $ret;
	}

	static public function getNotifyData($type, $config)
	{
		try {
			$instance = self::getInstance($type, $config);
			return $instance->getNotifyData();
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}
	}
}


?>
