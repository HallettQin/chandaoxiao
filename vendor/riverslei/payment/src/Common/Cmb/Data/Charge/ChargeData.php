<?php
//cgxlm
namespace Payment\Common\Cmb\Data\Charge;

class ChargeData extends \Payment\Common\Cmb\Data\CmbBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$amount = $this->amount;

		if (bccomp($amount, \Payment\Config::PAY_MIN_FEE, 2) === -1) {
			throw new \Payment\Common\PayException('支付金额不能低于 ' . \Payment\Config::PAY_MIN_FEE . ' 元');
		}

		$clientIp = $this->client_ip;

		if (empty($clientIp)) {
			$this->client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		}

		$timeExpire = $this->timeout_express;

		if (!empty($timeExpire)) {
			$express = floor(($timeExpire - strtotime($this->dateTime)) / 60);
			if ((\Payment\Common\CmbConfig::MAX_EXPIRE_TIME < $express) || ($express < 0)) {
				$this->timeout_express = \Payment\Common\CmbConfig::MAX_EXPIRE_TIME;
			}
			else {
				$this->timeout_express = $express;
			}
		}
	}

	protected function getReqData()
	{
		$reqData = array('dateTime' => $this->dateTime, 'branchNo' => $this->branchNo, 'merchantNo' => $this->merchantNo, 'date' => $this->date ? $this->date : date('Ymd', time()), 'orderNo' => $this->order_no, 'amount' => $this->amount, 'expireTimeSpan' => $this->timeout_express ? $this->timeout_express : '', 'payNoticeUrl' => $this->notifyUrl, 'payNoticePara' => $this->return_param ? $this->return_param : '', 'returnUrl' => $this->returnUrl ? $this->returnUrl : '', 'clientIP' => $this->client_ip, 'cardType' => $this->limitPay ? $this->limitPay : '', 'agrNo' => $this->agr_no, 'merchantSerialNo' => $this->serial_no ? $this->serial_no : '', 'userID' => $this->user_id ? $this->user_id : '', 'mobile' => $this->mobile ? $this->mobile : '', 'lon' => $this->lon ? $this->lon : '', 'lat' => $this->lat ? $this->lat : '', 'riskLevel' => $this->risk_level ? $this->risk_level : '', 'signNoticeUrl' => $this->signNoticeUrl ? $this->signNoticeUrl : '', 'signNoticePara' => $this->return_param ? $this->return_param : '', 'extendInfo' => '', 'extendInfoEncrypType' => '');
		return $reqData;
	}
}

?>
