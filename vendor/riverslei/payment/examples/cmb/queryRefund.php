<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$cmbConfig = require_once __DIR__ . '/../cmbconfig.php';
$data = array('out_trade_no' => '9354737499', 'refund_no' => '', 'date' => '20170430', 'refund_id' => '');

try {
	$ret = \Payment\Client\Query::run(\Payment\Config::CMB_REFUND, $cmbConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
