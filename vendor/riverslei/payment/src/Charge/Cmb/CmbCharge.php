<?php
//cgxlm
namespace Payment\Charge\Cmb;

class CmbCharge extends \Payment\Common\Cmb\CmbBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->getewayUrl = 'https://netpay.cmbchina.com/netpayment/BaseHttp.dll?MB_EUserPay';

		if ($this->config->useSandbox) {
			$this->config->getewayUrl = 'http://121.15.180.66:801/NetPayment/BaseHttp.dll?MB_EUserPay';
		}

		return 'Payment\\Common\\Cmb\\Data\\Charge\\ChargeData';
	}
}

?>
