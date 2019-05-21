<?php
//cgxlm
namespace Payment\Common\Weixin\Data\Charge;

abstract class ChargeBaseData extends \Payment\Common\Weixin\Data\WxBaseData
{
	protected function checkDataParam()
	{
		$orderNo = $this->order_no;
		$amount = $this->amount;
		$subject = $this->subject;
		$body = $this->body;
		$deviceInfo = $this->terminal_id;
		if (empty($orderNo) || (64 < mb_strlen($orderNo))) {
			throw new \Payment\Common\PayException('订单号不能为空，并且长度不能超过64位');
		}

		if (bccomp($amount, \Payment\Config::PAY_MIN_FEE, 2) === -1) {
			throw new \Payment\Common\PayException('支付金额不能低于 ' . \Payment\Config::PAY_MIN_FEE . ' 元');
		}

		if (empty($subject) || empty($body)) {
			throw new \Payment\Common\PayException('必须提供商品名称与商品详情');
		}

		if (($this->timeout_express - strtotime($this->timeStart)) < 5) {
			throw new \Payment\Common\PayException('必须设置订单过期时间,且需要大于5分钟.如果不正确请检查是否正确设置时区');
		}
		else {
			$this->timeout_express = date('YmdHis', $this->timeout_express);
		}

		$this->amount = bcmul($amount, 100, 0);
		$clientIp = $this->client_ip;

		if (empty($clientIp)) {
			$this->client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		}

		if (empty($deviceInfo)) {
			$this->terminal_id = 'WEB';
		}
	}
}

?>
