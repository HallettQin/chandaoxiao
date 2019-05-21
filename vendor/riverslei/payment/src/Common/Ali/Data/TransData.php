<?php
//cgxlm
namespace Payment\Common\Ali\Data;

class TransData extends AliBaseData
{
	protected function checkDataParam()
	{
		$transNo = $this->trans_no;
		$payeeType = $this->payee_type;
		$payeeAccount = $this->payee_account;
		$amount = $this->amount;
		$remark = $this->remark;

		if (empty($transNo)) {
			throw new \Payment\Common\PayException('请传入 商户转账唯一订单号');
		}

		if (empty($payeeType) || !in_array($payeeType, array('ALIPAY_USERID', 'ALIPAY_LOGONID'))) {
			throw new \Payment\Common\PayException('请传入收款账户类型');
		}

		if (empty($payeeAccount)) {
			throw new \Payment\Common\PayException('请传入转账帐号');
		}

		if (empty($amount) || (bccomp($amount, 0, 2) !== 1)) {
			throw new \Payment\Common\PayException('请输入转账金额，且大于0');
		}

		if ((bccomp($amount, \Payment\Config::TRANS_FEE, 2) !== -1) && empty($remark)) {
			throw new \Payment\Common\PayException('转账金额大于等于' . \Payment\Config::TRANS_FEE, '必须设置 remark');
		}
	}

	protected function buildData()
	{
		$signData = array('app_id' => $this->appId, 'method' => $this->method, 'format' => $this->format, 'charset' => $this->charset, 'sign_type' => $this->signType, 'timestamp' => $this->timestamp, 'version' => $this->version, 'biz_content' => $this->getBizContent());
		$this->retData = \Payment\Utils\ArrayUtil::paraFilter($signData);
	}

	private function getBizContent()
	{
		$content = array('out_biz_no' => $this->trans_no, 'payee_type' => strtoupper($this->payee_type), 'payee_account' => $this->payee_account, 'amount' => $this->amount, 'payer_real_name' => $this->payer_real_name, 'payer_show_name' => $this->payer_show_name, 'payee_real_name' => $this->payee_real_name, 'remark' => $this->remark);
		$content = \Payment\Utils\ArrayUtil::paraFilter($content);
		return json_encode($content, JSON_UNESCAPED_UNICODE);
	}
}

?>
