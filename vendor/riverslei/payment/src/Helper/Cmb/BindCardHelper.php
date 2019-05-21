<?php
//cgxlm
namespace Payment\Helper\Cmb;

class BindCardHelper extends \Payment\Common\Cmb\CmbBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->getewayUrl = 'https://mobile.cmbchina.com/mobilehtml/DebitCard/M_NetPay/OneNetRegister/NP_BindCard.aspx';

		if ($this->config->useSandbox) {
			$this->config->getewayUrl = 'http://121.15.180.66:801/mobilehtml/DebitCard/M_NetPay/OneNetRegister/NP_BindCard.aspx';
		}

		return 'Payment\\Common\\Cmb\\Data\\BindCardData';
	}
}

?>
