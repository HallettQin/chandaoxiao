<?php
//cgxlm
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/testNotify.php';
date_default_timezone_set('Asia/Shanghai');
$aliConfig = require_once __DIR__ . '/aliconfig.php';
$wxConfig = require_once __DIR__ . '/wxconfig.php';
$cmbConfig = require_once __DIR__ . '/cmbconfig.php';
$callback = new TestNotify();
$type = 'cmb_charge';

if (stripos($type, 'ali') !== false) {
	$config = $aliConfig;
}
else if (stripos($type, 'wx') !== false) {
	$config = $wxConfig;
}
else {
	$config = $cmbConfig;
}

try {
	$ret = \Payment\Client\Notify::run($type, $config, $callback);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

var_dump($ret);
exit();

?>
