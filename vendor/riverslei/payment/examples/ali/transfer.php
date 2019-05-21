<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/../aliconfig.php';
$data = array('trans_no' => time(), 'payee_type' => 'ALIPAY_LOGONID', 'payee_account' => 'aaqlmq0729@sandbox.com', 'amount' => '0.01', 'remark' => '转账拉，有钱了', 'payer_show_name' => '何磊');

try {
	$ret = \Payment\Client\Transfer::run(\Payment\Config::ALI_TRANSFER, $aliConfig, $data);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);

?>
