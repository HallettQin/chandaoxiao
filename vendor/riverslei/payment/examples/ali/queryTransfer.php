<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/../aliconfig.php';
$data = array('trans_no' => '1493546355', 'transaction_id' => '20170430110070001500680000004577');

try {
	$ret = \Payment\Client\Query::run(\Payment\Config::ALI_TRANSFER, $aliConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
