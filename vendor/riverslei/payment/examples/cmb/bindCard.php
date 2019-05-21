<?php
//cgxlm
require_once __DIR__ . '/../../autoload.php';
date_default_timezone_set('Asia/Shanghai');
$cmbConfig = require_once __DIR__ . '/../cmbconfig.php';
$signData = array('date' => date('Ymd'), 'agr_no' => '430802198004014374', 'serial_no' => time() . rand(1000, 9999), 'mobile' => '13500007108', 'user_id' => '100', 'lon' => '', 'lat' => '', 'riskLevel' => '1');

try {
	$data = \Payment\Client\Helper::run(\Payment\Config::CMB_BIND, $cmbConfig, $signData);
}
catch (\Payment\Common\PayException $e) {
	echo $e->errorMessage();
	exit();
}

$btnText = '点我开始签约';
echo "\n<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>签约一网通支付</title>\n    <meta content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\" name=\"viewport\">\n    <meta content=\"telephone=no\" name=\"format-detection\">\n    <style>\n        .box{\n            padding:6px 10px\n        }\n        .button {\n            color: #f5efef;\n            background-color: #10a737;\n            border-color: #EEE;\n            font-weight: 300;\n            font-size: 16px;\n            font-family: \"Helvetica Neue Light\", \"Helvetica Neue\", Helvetica, Arial, \"Lucida Grande\", sans-serif;\n            text-decoration: none;\n            text-align: center;\n            line-height: 40px;\n            height: 100px;\n            padding: 0 40px;\n            margin: 0;\n            width: 100%;\n            display: inline-block;\n            appearance: none;\n            cursor: pointer;\n            border: none;\n            -webkit-box-sizing: border-box;\n            -moz-box-sizing: border-box;\n            box-sizing: border-box;\n            -webkit-transition-property: all;\n            transition-property: all;\n            -webkit-transition-duration: .3s;\n            transition-duration: .3s;\n        }\n        .button-rounded {\n            border-radius: 4px;\n        }\n        .button-uppercase {\n            text-transform: uppercase;\n        }\n    </style>\n</head>\n<body>\n<div class=\"box\">\n    <form method=\"post\" action=\"";
echo $data['url'];
echo "\">\n        <input type=\"hidden\" name=\"";
echo $data['name'];
echo '" value=\'';
echo $data['value'];
echo "'>\n        <button type=\"submit\" class=\"button button-rounded button-uppercase\">";
echo $btnText;
echo "</button>\n    </form>\n</div>\n</body>\n</html>";

?>
