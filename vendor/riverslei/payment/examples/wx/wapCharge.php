<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$wxConfig = require_once __DIR__ . '/../wxconfig.php';
$orderNo = time() . rand(1000, 9999);
$payData = array(
	'body'            => 'test body',
	'subject'         => 'test subject',
	'order_no'        => $orderNo,
	'timeout_express' => time() + 600,
	'amount'          => '3.01',
	'return_param'    => '123',
	'client_ip'       => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
	'scene_info'      => array('type' => 'Wap', 'wap_url' => 'helei112g.github.io', 'wap_name' => '测试充值')
	);

try {
	$url = \Payment\Client\Charge::run(\Payment\Config::WX_CHANNEL_WAP, $wxConfig, $payData);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo $url;

?>
