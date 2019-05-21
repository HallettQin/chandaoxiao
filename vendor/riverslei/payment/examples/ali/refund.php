<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/../aliconfig.php';
$refundNo = time() . rand(1000, 9999);
$data = array('out_trade_no' => '14935460661343', 'refund_fee' => '0.01', 'reason' => '测试帐号退款', 'refund_no' => $refundNo);

try {
	$ret = \Payment\Client\Refund::run(\Payment\Config::ALI_REFUND, $aliConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
