<?php
//cgxlm
namespace Payment\Common\Cmb\Data;

class PubKeyData extends CmbBaseData
{
	protected function getReqData()
	{
		$reqData = array('dateTime' => $this->dateTime, 'branchNo' => $this->branchNo, 'merchantNo' => $this->merchantNo, 'txCode' => \Payment\Common\CmbConfig::TRADE_CODE);
		return $reqData;
	}
}

?>
