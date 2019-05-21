<?php
//cgxlm
ob_start();
include dirname(__FILE__) . '/res/exemple07a.php';
include dirname(__FILE__) . '/res/exemple07b.php';
$content = ob_get_clean();
require_once dirname(__FILE__) . '/../html2pdf.class.php';

try {
	$html2pdf = new HTML2PDF('P', 'A4', 'fr');
	$html2pdf->pdf->SetDisplayMode('fullpage');
	$html2pdf->writeHTML($content, isset($_GET['vuehtml']));
	$html2pdf->Output('exemple07.pdf');
}
catch (HTML2PDF_exception $e) {
	echo $e;
	exit();
}

?>
