<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/../aliconfig.php';
$data = array('out_trade_no' => '14935460661343', 'trade_no' => '', 'refund_no' => '14935460994756');

try {
	$ret = \Payment\Client\Query::run(\Payment\Config::ALI_CHARGE, $aliConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
