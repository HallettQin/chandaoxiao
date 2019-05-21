<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Query;

class ChargeQueryData extends \Payment\Common\Weixin\Data\WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('appid' => $this->appId, 'mch_id' => $this->mchId, 'nonce_str' => $this->nonceStr, 'sign_type' => $this->signType, 'transaction_id' => $this->transaction_id, 'out_trade_no' => $this->out_trade_no);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($this->retData);
	}

	protected function checkDataParam()
	{
		$transaction_id = $this->transaction_id;
		$order_no = $this->out_trade_no;
		if (empty($transaction_id) && empty($order_no)) {
			throw new \Payment\Common\PayException('必须提供微信交易号或商户网站唯一订单号。建议使用微信交易号');
		}
	}
}

?>
