<?php
//cgxlm
function customSetup($barcode, $get)
{
	if (isset($get['checksum'])) {
		$barcode->setChecksum($get['checksum'] === '1' ? true : false);
	}
}

$classFile = 'BCGmsi.barcode.php';
$className = 'BCGmsi';
$baseClassFile = 'BCGBarcode1D.php';
$codeVersion = '5.2.0';

?>
