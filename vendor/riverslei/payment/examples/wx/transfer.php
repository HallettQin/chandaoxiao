<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$wxConfig = require_once __DIR__ . '/../wxconfig.php';
$data = array('trans_no' => time(), 'openid' => 'o-e_mwTXTaxEhBM8xDoj1ui1f950', 'check_name' => 'NO_CHECK', 'payer_real_name' => '何磊', 'amount' => '1', 'desc' => '测试转账', 'spbill_create_ip' => '127.0.0.1');

try {
	$ret = \Payment\Client\Transfer::run(\Payment\Config::WX_TRANSFER, $wxConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
