<?php
//cgxlm
namespace Payment\Common\Ali\Data\Query;

class ChargeQueryData extends QueryBaseData
{
	protected function checkDataParam()
	{
		$tradeNo = $this->trade_no;
		$outTradeNo = $this->out_trade_no;
		if (empty($outTradeNo) && empty($tradeNo)) {
			throw new \Payment\Common\PayException('必须提供支付宝交易号或者商户网站唯一订单号。建议使用支付宝交易号');
		}
	}

	protected function getBizContent()
	{
		$content = array('out_trade_no' => $this->out_trade_no, 'trade_no' => $this->trade_no);
		$content = \Payment\Utils\ArrayUtil::paraFilter($content);
		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}
}

?>
