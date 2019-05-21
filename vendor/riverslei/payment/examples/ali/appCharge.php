<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/../aliconfig.php';
$orderNo = time() . rand(1000, 9999);
$payData = array('body' => 'ali qr pay', 'subject' => '测试支付宝扫码支付', 'order_no' => $orderNo, 'timeout_express' => time() + 600, 'amount' => '0.01', 'return_param' => '123123', 'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1', 'goods_type' => '1', 'store_id' => '');

try {
	$str = \Payment\Client\Charge::run(\Payment\Config::ALI_CHANNEL_APP, $aliConfig, $payData);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo $str;

?>
