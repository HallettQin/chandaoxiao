<?php
//cgxlm
namespace Payment;

class QueryContext
{
	/**
     * 查询的渠道
     * @var BaseStrategy
     */
	protected $query;

	public function initQuery($channel, array $config)
	{
		try {
			switch ($channel) {
			case Config::ALI_CHARGE:
				$this->query = new Query\Ali\AliChargeQuery($config);
				break;

			case Config::ALI_REFUND:
				$this->query = new Query\Ali\AliRefundQuery($config);
				break;

			case Config::ALI_TRANSFER:
				$this->query = new Query\Ali\AliTransferQuery($config);
				break;

			case Config::WX_CHARGE:
				$this->query = new Query\Wx\WxChargeQuery($config);
				break;

			case Config::WX_REFUND:
				$this->query = new Query\Wx\WxRefundQuery($config);
				break;

			case Config::WX_TRANSFER:
				$this->query = new Query\Wx\WxTransferQuery($config);
				break;

			case Config::CMB_CHARGE:
				$this->query = new Query\Cmb\CmbChargeQuery($config);
				break;

			case Config::CMB_REFUND:
				$this->query = new Query\Cmb\CmbRefundQuery($config);
				break;

			default:
				throw new Common\PayException('当前仅支持：ALI_CHARGE ALI_REFUND WX_CHARGE WX_REFUND WX_TRANSFER CMB_CHARGE CMB_REFUND');
			}
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}

	public function query(array $data)
	{
		if (!$this->query instanceof Common\BaseStrategy) {
			throw new Common\PayException('请检查初始化是否正确');
		}

		try {
			return $this->query->handle($data);
		}
		catch (Common\PayException $e) {
			throw $e;
		}
	}
}


?>
