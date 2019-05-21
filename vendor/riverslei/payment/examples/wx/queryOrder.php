<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$wxConfig = require_once __DIR__ . '/../wxconfig.php';
$data = array('out_trade_no' => '14935505602169', 'transaction_id' => '20170430190922203640695');

try {
	$ret = \Payment\Client\Query::run(\Payment\Config::WX_CHARGE, $wxConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
