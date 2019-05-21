<?php
//cgxlm
function customSetup($barcode, $get)
{
	if (isset($get['label'])) {
		$barcode->setLabel($get['label']);
	}
}

$classFile = 'BCGothercode.barcode.php';
$className = 'BCGothercode';
$baseClassFile = 'BCGBarcode1D.php';
$codeVersion = '5.2.0';

?>
