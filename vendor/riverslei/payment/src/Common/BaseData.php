<?php
//cgxlm
namespace Payment\Common;

abstract class BaseData
{
	/**
     * 支付的请求数据
     * @var array $data
     */
	protected $data;
	/**
     * 支付返回的数据
     * @var array $retData
     */
	protected $retData;
	/**
     * 配置类型
     * @var string $configType
     */
	protected $channel;

	public function __construct(ConfigInterface $config, array $reqData)
	{
		if ($config instanceof WxConfig) {
			$this->channel = \Payment\Config::WECHAT_PAY;
		}
		else if ($config instanceof AliConfig) {
			$this->channel = \Payment\Config::ALI_PAY;
		}
		else if ($config instanceof CmbConfig) {
			$this->channel = \Payment\Config::CMB_PAY;
		}

		$this->data = array_merge($config->toArray(), $reqData);

		try {
			$this->checkDataParam();
		}
		catch (PayException $e) {
			throw $e;
		}
	}

	public function __get($name)
	{
		if (isset($this->data[$name])) {
			return $this->data[$name];
		}

		return null;
	}

	public function __set($name, $value)
	{
		$this->data[$name] = $value;
	}

	public function setSign()
	{
		$this->buildData();

		if ($this->channel === \Payment\Config::CMB_PAY) {
			$data = $this->retData['reqData'];
		}
		else {
			$data = $this->retData;
		}

		$values = \Payment\Utils\ArrayUtil::removeKeys($data, array('sign'));
		$values = \Payment\Utils\ArrayUtil::arraySort($values);
		$signStr = \Payment\Utils\ArrayUtil::createLinkstring($values);
		$this->retData['sign'] = $this->makeSign($signStr);
	}

	public function getData()
	{
		return $this->retData;
	}

	abstract protected function makeSign($signStr);

	abstract protected function buildData();

	abstract protected function checkDataParam();
}


?>
