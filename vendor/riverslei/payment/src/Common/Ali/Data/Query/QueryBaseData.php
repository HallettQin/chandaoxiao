<?php
//cgxlm
namespace Payment\Common\Ali\Data\Query;

abstract class QueryBaseData extends \Payment\Common\Ali\Data\AliBaseData
{
	protected function buildData()
	{
		$signData = array('app_id' => $this->appId, 'method' => $this->method, 'format' => $this->format, 'charset' => $this->charset, 'sign_type' => $this->signType, 'timestamp' => $this->timestamp, 'version' => $this->version, 'biz_content' => $this->getBizContent());
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}

	abstract protected function getBizContent();
}

?>
