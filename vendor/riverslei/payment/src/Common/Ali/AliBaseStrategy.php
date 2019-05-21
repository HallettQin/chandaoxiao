<?php
//cgxlm
namespace Payment\Common\Ali;

abstract class AliBaseStrategy implements \Payment\Common\BaseStrategy
{
	/**
     * 支付宝的配置文件
     * @var AliConfig $config
     */
	protected $config;
	/**
     * 支付数据
     * @var BaseData $reqData
     */
	protected $reqData;

	public function __construct(array $config)
	{
		mb_internal_encoding('UTF-8');

		try {
			$this->config = new \Payment\Common\AliConfig($config);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}
	}

	public function handle(array $data)
	{
		$buildClass = $this->getBuildDataClass();

		try {
			$this->reqData = new $buildClass($this->config, $data);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		$this->reqData->setSign();
		$data = $this->reqData->getData();
		return $this->retData($data);
	}

	protected function sendReq($url)
	{
		$curl = new \Payment\Utils\Curl();
		$responseTxt = $curl->set(array('CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2, 'CURLOPT_HEADER' => 0))->get($url);

		if ($responseTxt['error']) {
			throw new \Payment\Common\PayException('网络发生错误，请稍后再试curl返回码：' . $responseTxt['message']);
		}

		$body = $responseTxt['body'];
		$responseKey = str_ireplace('.', '_', $this->config->method . '.response');
		$body = json_decode($body, true);

		if ($body[$responseKey]['code'] != 10000) {
			throw new \Payment\Common\PayException($body[$responseKey]['sub_msg']);
		}

		$flag = $this->verifySign($body[$responseKey], $body['sign']);

		if (!$flag) {
			throw new \Payment\Common\PayException('支付宝返回数据被篡改。请检查网络是否安全！');
		}

		return $body[$responseKey];
	}

	protected function retData(array $data)
	{
		$sign = $data['sign'];
		$data = \Payment\Utils\ArrayUtil::removeKeys($data, array('sign'));
		$data = \Payment\Utils\ArrayUtil::arraySort($data);

		foreach ($data as &$value) {
			$value = \Payment\Utils\StrUtil::characet($value, $this->config->charset);
		}

		$data['sign'] = $sign;
		return $this->config->getewayUrl . http_build_query($data);
	}

	protected function getTradeStatus($status)
	{
		switch ($status) {
		case 'TRADE_SUCCESS':
		case 'TRADE_FINISHED':
			return \Payment\Config::TRADE_STATUS_SUCC;
		case 'WAIT_BUYER_PAY':
		case 'TRADE_CLOSED':
		default:
			return \Payment\Config::TRADE_STATUS_FAILD;
		}
	}

	protected function verifySign(array $data, $sign)
	{
		$preStr = json_encode($data);

		if ($this->config->signType === 'RSA') {
			$rsa = new \Payment\Utils\RsaEncrypt($this->config->rsaAliPubKey);
			return $rsa->rsaVerify($preStr, $sign);
		}
		else if ($this->config->signType === 'RSA2') {
			$rsa = new \Payment\Utils\Rsa2Encrypt($this->config->rsaAliPubKey);
			return $rsa->rsaVerify($preStr, $sign);
		}
		else {
			return false;
		}
	}
}

?>
