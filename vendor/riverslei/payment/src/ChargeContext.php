<?php
//cgxlm
namespace Payment;

class ChargeContext
{
	/**
     * 支付的渠道
     * @var BaseStrategy
     */
	protected $channel;

	public function initCharge($channel, array $config)
	{
		try {
			switch ($channel) {
			case Config::ALI_CHANNEL_WAP:
				$this->channel = new Charge\Ali\AliWapCharge($config);
				break;

			case Config::ALI_CHANNEL_APP:
				$this->channel = new Charge\Ali\AliAppCharge($config);
				break;

			case Config::ALI_CHANNEL_WEB:
				$this->channel = new Charge\Ali\AliWebCharge($config);
				break;

			case Config::ALI_CHANNEL_QR:
				$this->channel = new Charge\Ali\AliQrCharge($config);
				break;

			case Config::ALI_CHANNEL_BAR:
				$this->channel = new Charge\Ali\AliBarCharge($config);
				break;

			case Config::WX_CHANNEL_APP:
				$this->channel = new Charge\Wx\WxAppCharge($config);
				break;

			case Config::WX_CHANNEL_LITE:
			case Config::WX_CHANNEL_PUB:
				$this->channel = new Charge\Wx\WxPubCharge($config);
				break;

			case Config::WX_CHANNEL_WAP:
				$this->channel = new Charge\Wx\WxWapCharge($config);
				break;

			case Config::WX_CHANNEL_QR:
				$this->channel = new Charge\Wx\WxQrCharge($config);
				break;

			case Config::WX_CHANNEL_BAR:
				$this->channel = new Charge\Wx\WxBarCharge($config);
				break;

			case Config::CMB_CHANNEL_WAP:
			case Config::CMB_CHANNEL_APP:
				$this->channel = new Charge\Cmb\CmbCharge($config);
				break;

			default:
				throw new Common\PayException('当前仅支持：支付宝  微信 招商一网通');
			}
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}

	public function charge(array $data)
	{
		if (!$this->channel instanceof Common\BaseStrategy) {
			throw new Common\PayException('请检查初始化是否正确');
		}

		try {
			return $this->channel->handle($data);
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}
}


?>
