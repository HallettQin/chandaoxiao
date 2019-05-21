<?php
//cgxlm
function customSetup($barcode, $get)
{
	if (isset($get['barcodeIdentifier']) && isset($get['serviceType']) && isset($get['mailerIdentifier']) && isset($get['serialNumber'])) {
		$barcode->setTrackingCode(intval($get['barcodeIdentifier']), intval($get['serviceType']), intval($get['mailerIdentifier']), intval($get['serialNumber']));
	}
}

$classFile = 'BCGintelligentmail.barcode.php';
$className = 'BCGintelligentmail';
$baseClassFile = 'BCGBarcode1D.php';
$codeVersion = '5.2.0';

?>
