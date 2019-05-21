<?php
//cgxlm
return array(
	'use_sandbox'  => true,
	'app_id'       => 'wxxxxxx',
	'mch_id'       => 'xxxxx',
	'md5_key'      => 'xxxxxxx',
	'app_cert_pem' => dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wx' . DIRECTORY_SEPARATOR . 'pem' . DIRECTORY_SEPARATOR . 'weixin_app_cert.pem',
	'app_key_pem'  => dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wx' . DIRECTORY_SEPARATOR . 'pem' . DIRECTORY_SEPARATOR . 'weixin_app_key.pem',
	'sign_type'    => 'MD5',
	'limit_pay'    => array(),
	'fee_type'     => 'CNY',
	'notify_url'   => 'https://helei112g.github.io/v1/notify/wx',
	'redirect_url' => 'https://helei112g.github.io/',
	'return_raw'   => false
	);

?>
