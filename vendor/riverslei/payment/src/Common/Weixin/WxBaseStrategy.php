<?php
//cgxlm
namespace Payment\Common\Weixin;

abstract class WxBaseStrategy implements \Payment\Common\BaseStrategy
{
	/**
     * 支付宝的配置文件
     * @var WxConfig $config
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
			$this->config = new \Payment\Common\WxConfig($config);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}
	}

	protected function sendReq($xml)
	{
		$url = $this->getReqUrl();

		if (is_null($url)) {
			throw new \Payment\Common\PayException('目前不支持该接口。请联系开发者添加');
		}

		if ($this->config->useSandbox) {
			$url = str_ireplace('{debug}', \Payment\Common\WxConfig::SANDBOX_PRE, $url);
		}
		else {
			$url = str_ireplace('{debug}/', '', $url);
		}

		$responseTxt = $this->curlPost($xml, $url);

		if ($responseTxt['error']) {
			throw new \Payment\Common\PayException('网络发生错误，请稍后再试curl返回码：' . $responseTxt['message']);
		}

		$retData = \Payment\Utils\DataParser::toArray($responseTxt['body']);

		if ($retData['return_code'] != 'SUCCESS') {
			throw new \Payment\Common\PayException('微信返回错误提示:' . $retData['return_msg']);
		}

		if ($retData['result_code'] != 'SUCCESS') {
			$msg = ($retData['err_code_des'] ? $retData['err_code_des'] : $retData['err_msg']);
			throw new \Payment\Common\PayException('微信返回错误提示:' . $msg);
		}

		return $retData;
	}

	protected function curlPost($xml, $url)
	{
		$curl = new \Payment\Utils\Curl();
		return $curl->set(array('CURLOPT_HEADER' => 0))->post($xml)->submit($url);
	}

	protected function getReqUrl()
	{
		return \Payment\Common\WxConfig::UNIFIED_URL;
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
		$xml = \Payment\Utils\DataParser::toXml($this->reqData->getData());
		$ret = $this->sendReq($xml);
		$flag = $this->verifySign($ret);

		if (!$flag) {
			throw new \Payment\Common\PayException('微信返回数据被篡改。请检查网络是否安全！');
		}

		return $this->retData($ret);
	}

	protected function retData(array $ret)
	{
		return $ret;
	}

	protected function verifySign(array $retData)
	{
		$retSign = $retData['sign'];
		$values = \Payment\Utils\ArrayUtil::removeKeys($retData, array('sign', 'sign_type'));
		$values = \Payment\Utils\ArrayUtil::paraFilter($values);
		$values = \Payment\Utils\ArrayUtil::arraySort($values);
		$signStr = \Payment\Utils\ArrayUtil::createLinkstring($values);
		$signStr .= '&key=' . $this->config->md5Key;

		switch ($this->config->signType) {
		case 'MD5':
			$sign = md5($signStr);
			break;

		case 'HMAC-SHA256':
			$sign = hash_hmac('sha256', $signStr, $this->config->md5Key);
			break;

		default:
			$sign = '';
		}

		return strtoupper($sign) === $retSign;
	}
}

?>
