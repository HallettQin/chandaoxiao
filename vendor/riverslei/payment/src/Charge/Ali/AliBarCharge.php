<?php
//cgxlm
namespace Payment\Charge\Ali;

class AliBarCharge extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::BAR_PAY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Charge\\BarChargeData';
	}

	protected function retData(array $ret)
	{
		$url = parent::retData($ret);

		try {
			$data = $this->sendReq($url);
		}
		catch (\Payment\Common\PayException $e) {
			throw $e;
		}

		return $data;
	}
}

?>
