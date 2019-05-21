<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$cmbConfig = require_once __DIR__ . '/../cmbconfig.php';
$refundNo = time() . rand(1000, 9999);
$data = array('out_trade_no' => '9354737499', 'date' => '20170430', 'refund_no' => $refundNo, 'refund_fee' => 0.01, 'reason' => '测试帐号退款', 'operator_id' => '9999');

try {
	$ret = \Payment\Client\Refund::run(\Payment\Config::CMB_REFUND, $cmbConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
