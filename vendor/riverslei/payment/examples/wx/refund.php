<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$wxConfig = require_once __DIR__ . '/../wxconfig.php';
$refundNo = time() . rand(1000, 9999);
$data = array('out_trade_no' => '14935385689468', 'total_fee' => '3.01', 'refund_fee' => '3.01', 'refund_no' => $refundNo, 'refund_account' => \Payment\Common\WxConfig::REFUND_RECHARGE);

try {
	$ret = \Payment\Client\Refund::run(\Payment\Config::WX_REFUND, $wxConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
