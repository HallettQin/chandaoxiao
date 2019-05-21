<?php
//cgxlm
namespace Payment\Common;

interface BaseStrategy
{
	public function handle(array $data);

	public function getBuildDataClass();
}


?>
