<?php
//cgxlm
namespace Payment\Common\Weixin;

class WechatHelper extends Data\WxBaseData
{
	public function getSandboxSignKey()
	{
		$this->setSign();
		$xml = \Payment\Utils\DataParser::toXml($this->getData());
		$url = \Payment\Common\WxConfig::SANDBOX_URL;
		$curl = new \Payment\Utils\Curl();
		$responseTxt = $curl->set(array('CURLOPT_HEADER' => 0))->post($xml)->submit($url);

		if ($responseTxt['error']) {
			throw new \Payment\Common\PayException('网络发生错误，请稍后再试curl返回码：' . $responseTxt['message']);
		}

		$retData = \Payment\Utils\DataParser::toArray($responseTxt['body']);

		if ($retData['return_code'] != 'SUCCESS') {
			throw new \Payment\Common\PayException('微信返回错误提示:' . $retData['return_msg']);
		}

		return $retData['sandbox_signkey'];
	}

	protected function buildData()
	{
		$this->retData = array('mch_id' => $this->mchId, 'nonce_str' => $this->nonceStr);
	}

	protected function checkDataParam()
	{
	}
}

?>
