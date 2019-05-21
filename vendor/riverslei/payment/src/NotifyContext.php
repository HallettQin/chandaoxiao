<?php
//cgxlm
namespace Payment;

class NotifyContext
{
	/**
     * 支付的渠道
     * @var NotifyStrategy
     */
	protected $notify;

	public function initNotify($channel, array $config)
	{
		try {
			switch ($channel) {
			case Config::ALI_CHARGE:
				$this->notify = new Notify\AliNotify($config);
				break;

			case Config::WX_CHARGE:
				$this->notify = new Notify\WxNotify($config);
				break;

			case Config::CMB_CHARGE:
				$this->notify = new Notify\CmbNotify($config);
				break;

			default:
				throw new Common\PayException('当前仅支持：ALI_CHARGE WX_CHARGE CMB_CHARGE 常量');
			}
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}

	public function getNotifyData()
	{
		return $this->notify->getNotifyData();
	}

	public function notify(Notify\PayNotifyInterface $notify)
	{
		if (!$this->notify instanceof Notify\NotifyStrategy) {
			throw new Common\PayException('请检查初始化是否正确');
		}

		return $this->notify->handle($notify);
	}
}


?>
