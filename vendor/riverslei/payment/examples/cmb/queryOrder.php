<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$cmbConfig = require_once __DIR__ . '/../cmbconfig.php';
$data = array('out_trade_no' => '9336161758', 'date' => '20170428', 'transaction_id' => '17242823500000000010');

try {
	$ret = \Payment\Client\Query::run(\Payment\Config::CMB_CHARGE, $cmbConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
