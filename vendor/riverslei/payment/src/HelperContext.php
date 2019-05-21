<?php
//cgxlm
namespace Payment;

class HelperContext
{
	/**
     * 转款渠道
     * @var BaseStrategy
     */
	protected $helper;

	public function initHelper($way, array $config)
	{
		try {
			switch ($way) {
			case Config::CMB_BIND:
				$this->helper = new Helper\Cmb\BindCardHelper($config);
				break;

			case Config::CMB_PUB_KEY:
				$this->helper = new Helper\Cmb\PubKeyHelper($config);
				break;

			default:
				throw new Common\PayException('当前仅支持：CMB_BIND CMB_PUB_KEY 操作');
			}
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}

	public function helper(array $data)
	{
		if (!$this->helper instanceof Common\BaseStrategy) {
			throw new Common\PayException('请检查初始化是否正确');
		}

		try {
			return $this->helper->handle($data);
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}
}


?>
