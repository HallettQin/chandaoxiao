<?php
//cgxlm
namespace Illuminate\Contracts\Console;

interface Application
{
	public function call($command, array $parameters = array());

	public function output();
}


?>
