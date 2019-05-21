<?php
//cgxlm
namespace Payment;

class RefundContext
{
	/**
     * 退款的渠道
     * @var BaseStrategy
     */
	protected $refund;

	public function initRefund($channel, array $config)
	{
		try {
			switch ($channel) {
			case Config::ALI_REFUND:
				$this->refund = new Refund\AliRefund($config);
				break;

			case Config::WX_REFUND:
				$this->refund = new Refund\WxRefund($config);
				break;

			case Config::CMB_REFUND:
				$this->refund = new Refund\CmbRefund($config);
				break;

			default:
				throw new Common\PayException('当前仅支持：ALI WEIXIN CMB');
			}
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}

	public function refund(array $data)
	{
		if (!$this->refund instanceof Common\BaseStrategy) {
			throw new Common\PayException('请检查初始化是否正确');
		}

		try {
			return $this->refund->handle($data);
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}
}


?>
