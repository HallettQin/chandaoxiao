<?php
//cgxlm
namespace Payment\Common\Weixin\Data;

class TransferData extends WxBaseData
{
	protected function buildData()
	{
		$this->retData = array('mch_appid' => $this->appId, 'mchid' => $this->mchId, 'device_info' => $this->terminal_id, 'nonce_str' => $this->nonceStr, 'partner_trade_no' => $this->trans_no, 'openid' => $this->openid, 'check_name' => $this->check_name, 're_user_name' => $this->payer_real_name, 'amount' => $this->amount, 'desc' => $this->desc, 'spbill_create_ip' => $this->client_ip);
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($this->retData);
	}

	protected function checkDataParam()
	{
		$openId = $this->openid;
		$transNo = $this->trans_no;
		$checkName = $this->check_name;
		$realName = $this->payer_real_name;
		$amount = $this->amount;
		$clientIp = $this->client_ip;

		if (empty($openId)) {
			throw new \Payment\Common\PayException('商户appid下，某用户的openid 必须传入');
		}

		if (empty($transNo)) {
			throw new \Payment\Common\PayException('商户订单号，需保持唯一性');
		}

		if (($checkName !== 'NO_CHECK') && empty($realName)) {
			throw new \Payment\Common\PayException('请传入收款人真实姓名');
		}

		$this->amount = bcmul($amount, 100, 0);
		if (empty($amount) || ($amount < 0)) {
			throw new \Payment\Common\PayException('转账金额错误');
		}

		if (empty($clientIp)) {
			$this->client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		}
	}
}

?>
