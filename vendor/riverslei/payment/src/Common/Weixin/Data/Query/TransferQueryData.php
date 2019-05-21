<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Query;

class TransferQueryData extends \Payment\Common\Weixin\Data\WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('appid' => $this->appId, 'mch_id' => $this->mchId, 'nonce_str' => $this->nonceStr, 'partner_trade_no' => $this->trans_no);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($this->retData);
	}

	protected function checkDataParam()
	{
		$transNo = $this->trans_no;

		if (empty($transNo)) {
			throw new \Payment\Common\PayException('请提供商户调用企业付款API时使用的商户订单号');
		}
	}
}

?>
