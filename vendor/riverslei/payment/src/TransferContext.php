<?php
//cgxlm
namespace Payment;

class TransferContext
{
	/**
     * 转款渠道
     * @var BaseStrategy
     */
	protected $transfer;

	public function initTransfer($channel, array $config)
	{
		try {
			switch ($channel) {
			case Config::ALI_TRANSFER:
				$this->transfer = new Trans\AliTransfer($config);
				break;

			case Config::WX_TRANSFER:
				$this->transfer = new Trans\WxTransfer($config);
				break;

			default:
				throw new Common\PayException('当前仅支持：ALI WEIXIN两个常量');
			}
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}

	public function transfer(array $data)
	{
		if (!$this->transfer instanceof Common\BaseStrategy) {
			throw new Common\PayException('请检查初始化是否正确');
		}

		try {
			return $this->transfer->handle($data);
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}
}


?>
