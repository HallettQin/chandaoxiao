<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$wxConfig = require_once __DIR__ . '/../wxconfig.php';
$orderNo = time() . rand(1000, 9999);
$payData = array('body' => 'test body', 'subject' => 'test subject', 'order_no' => $orderNo, 'timeout_express' => time() + 600, 'amount' => '0.01', 'return_param' => '123', 'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1', 'openid' => '', 'product_id' => '123', 'terminal_id' => 'web', 'auth_code' => '1231212232323123123', 'sub_appid' => '', 'sub_mch_id' => '');

try {
	$ret = \Payment\Client\Charge::run(\Payment\Config::WX_CHANNEL_BAR, $wxConfig, $payData);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
