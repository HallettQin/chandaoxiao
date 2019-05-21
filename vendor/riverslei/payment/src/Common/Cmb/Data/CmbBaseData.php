<?php
//cgxlm
namespace Payment\Common\Cmb\Data;

abstract class CmbBaseData extends \Payment\Common\BaseData
{
	protected function makeSign($signStr)
	{
		switch ($this->signType) {
		case 'SHA-256':
			$sign = hash('sha256', $signStr . '&' . $this->merKey);
			break;

		default:
			$sign = '';
		}

		return $sign;
	}

	protected function buildData()
	{
		$signData = array('version' => $this->version, 'charset' => $this->charset, 'signType' => $this->signType, 'reqData' => $this->getReqData());
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}

	protected function checkDataParam()
	{
		$branchNo = $this->branchNo;
		$merchantNo = $this->merchantNo;
		if (empty($branchNo) || (mb_strlen($branchNo) !== 4)) {
			throw new \Payment\Common\PayException('商户分行号，4位数字');
		}

		if (empty($merchantNo) || (mb_strlen($merchantNo) !== 6)) {
			throw new \Payment\Common\PayException('商户号，6位数字');
		}
	}

	abstract protected function getReqData();
}

?>
