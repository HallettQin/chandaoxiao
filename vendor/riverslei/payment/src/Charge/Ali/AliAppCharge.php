<?php
//cgxlm
namespace Payment\Charge\Ali;

class AliAppCharge extends \Payment\Common\Ali\AliBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->method = \Payment\Common\AliConfig::APP_PAY_METHOD;
		return 'Payment\\Common\\Ali\\Data\\Charge\\AppChargeData';
	}

	protected function retData(array $data)
	{
		$sign = $data['sign'];
		unset($data['sign']);
		$data = \Payment\Utils\ArrayUtil::arraySort($data);

		foreach ($data as &$value) {
			$value = \Payment\Utils\StrUtil::characet($value, $this->config->charset);
		}

		$data['sign'] = $sign;
		return http_build_query($data);
	}
}

?>
