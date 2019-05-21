<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$wxConfig = require_once __DIR__ . '/../wxconfig.php';
$data = array('out_trade_no' => '14935385689468', 'refund_no' => '14935506214648', 'transaction_id' => '12345678920170430191024123337865', 'refund_id' => '1234567892017043019102412333');

try {
	$ret = \Payment\Client\Query::run(\Payment\Config::WX_REFUND, $wxConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
