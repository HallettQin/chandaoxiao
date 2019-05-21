<?php
//cgxlm
namespace Payment\Common\Cmb;

abstract class CmbBaseStrategy implements \Payment\Common\BaseStrategy
{
	/**
     * 招商的配置文件
     * @var CmbConfig $config
     */
	protected $config;
	/**
     * 请求数据
     * @var BaseData $reqData
     */
	protected $reqData;

	public function __construct(array $config)
	{
		mb_internal_encoding('UTF-8');

		try {
			$this->config = new \Payment\Common\CmbConfig($config);
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

	protected function retData(array $ret)
	{
		$json = json_encode($ret, JSON_UNESCAPED_UNICODE);
		$reqData = array('url' => $this->config->getewayUrl, 'name' => \Payment\Common\CmbConfig::REQ_FILED_NAME, 'value' => $json);
		return $reqData;
	}

	protected function sendReq($json)
	{
		$responseTxt = $this->curlPost($json, $this->config->getewayUrl);

		if ($responseTxt['error']) {
			throw new \Payment\Common\PayException('网络发生错误，请稍后再试curl返回码：' . $responseTxt['message']);
		}

		$body = json_decode($responseTxt['body'], true);
		$rspData = $body['rspData'];

		if ($rspData['rspCode'] !== \Payment\Common\CmbConfig::SUCC_TAG) {
			throw new \Payment\Common\PayException('招商返回错误提示：' . $rspData['rspMsg']);
		}

		return $rspData;
	}

	protected function curlPost($json, $url)
	{
		$curl = new \Payment\Utils\Curl();
		return $curl->set(array('CURLOPT_HEADER' => 0))->post($json)->submit($url);
	}

	protected function getTradeStatus($status)
	{
		switch ($status) {
		case '0':
			return \Payment\Config::TRADE_STATUS_SUCC;
		case '1':
		case '2':
		case '4':
		case '7':
		case '8':
		default:
			return \Payment\Config::TRADE_STATUS_FAILD;
		}
	}
}

?>
