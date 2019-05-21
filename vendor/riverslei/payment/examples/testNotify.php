<?php
//cgxlm
class TestNotify implements \Payment\Notify\PayNotifyInterface
{
	public function notifyProcess(array $data)
	{
		$channel = $data['channel'];

		if ($channel === \Payment\Config::ALI_CHARGE) {
		}
		else if ($channel === \Payment\Config::WX_CHARGE) {
		}
		else if ($channel === \Payment\Config::CMB_CHARGE) {
		}
		else if ($channel === \Payment\Config::CMB_BIND) {
		}

		return true;
	}
}

?>
