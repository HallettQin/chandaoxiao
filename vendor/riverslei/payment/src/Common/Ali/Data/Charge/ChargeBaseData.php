<?php
//cgxlm
namespace Payment\Common\Ali\Data\Charge;

abstract class ChargeBaseData extends \Payment\Common\Ali\Data\AliBaseData
{
	protected function buildData()
	{
		$signData = array('app_id' => $this->appId, 'method' => $this->method, 'format' => $this->format, 'return_url' => $this->returnUrl, 'charset' => $this->charset, 'sign_type' => $this->signType, 'timestamp' => $this->timestamp, 'version' => $this->version, 'notify_url' => $this->notifyUrl, 'biz_content' => $this->getBizContent());
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}

	abstract protected function getBizContent();

	protected function checkDataParam()
	{
		$subject = $this->subject;
		$orderNo = $this->order_no;
		$amount = $this->amount;
		$goodsType = $this->goods_type;
		$passBack = $this->return_param;
		if (empty($orderNo) || (64 < mb_strlen($orderNo))) {
			throw new \Payment\Common\PayException('订单号不能为空，并且长度不能超过64位');
		}

		if (bccomp($amount, \Payment\Config::PAY_MIN_FEE, 2) === -1) {
			throw new \Payment\Common\PayException('支付金额不能低于 ' . \Payment\Config::PAY_MIN_FEE . ' 元');
		}

		if (empty($subject)) {
			throw new \Payment\Common\PayException('必须提供 商品的标题/交易标题/订单标题/订单关键字 等');
		}

		if (empty($goodsType)) {
			$this->goods_type = 1;
		}
		else if (!in_array($goodsType, array(0, 1))) {
			throw new \Payment\Common\PayException('商品类型可取值为：0-虚拟类商品  1-实物类商品');
		}

		if (!empty($passBack) && !is_string($passBack)) {
			throw new \Payment\Common\PayException('回传参数必须是字符串');
		}

		$this->return_param = urlencode($passBack);
	}
}

?>
