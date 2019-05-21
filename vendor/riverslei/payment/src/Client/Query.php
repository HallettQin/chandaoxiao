<?php
//cgxlm
namespace Payment\Client;

class Query
{
	static protected $supportType = array(\Payment\Config::ALI_CHARGE, \Payment\Config::ALI_REFUND, \Payment\Config::ALI_TRANSFER, \Payment\Config::ALI_RED, \Payment\Config::WX_CHARGE, \Payment\Config::WX_REFUND, \Payment\Config::WX_RED, \Payment\Config::WX_TRANSFER, \Payment\Config::CMB_CHARGE, \Payment\Config::CMB_REFUND);
	/**
     * 异步通知类
     * @var QueryContext
     */
	static protected $instance;

	static protected function getInstance($queryType, $config)
	{
		if (is_null(self::$instance)) {
			static::$instance = new \Payment\QueryContext();

			try {
				static::$instance->initQuery($queryType, $config);
			}
			catch (\Payment\Common\PayException $e) {
				throw $e;
			}
		}

		return static::$instance;
	}

	static public function run($queryType, $config, $metadata)
	{
		if (!in_array($queryType, self::$supportType)) {
			throw new \Payment\Common\PayException('sdk当前不支持该类型查询，当前仅支持：' . implode(',', self::$supportType) . 68);
		}

		try {
			$instance = self::getInstance($queryType, $config);
			$ret = $instance->query($metadata);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $ret;
	}
}


?>
