<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/../aliconfig.php';
$orderNo = time() . rand(1000, 9999);
$payData = array('body' => 'ali wap pay', 'subject' => '测试支付宝手机网站支付', 'order_no' => $orderNo, 'timeout_express' => time() + 600, 'amount' => '0.01', 'return_param' => 'tata', 'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1', 'goods_type' => '1', 'store_id' => '');

try {
	$url = \Payment\Client\Charge::run(\Payment\Config::ALI_CHANNEL_WAP, $aliConfig, $payData);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

header('Location:' . $url);

?>
