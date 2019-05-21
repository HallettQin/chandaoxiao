<?php
//cgxlm
require 'include.php';
$mq = $c->notify();

while (1) {
	$data = $mq->get();
	echo $data;
	echo "\n";
	$data->ack();
}

?>
