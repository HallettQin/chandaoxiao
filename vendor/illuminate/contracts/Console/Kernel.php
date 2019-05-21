<?php
//cgxlm
namespace Illuminate\Contracts\Console;

interface Kernel
{
	public function handle($input, $output = NULL);

	public function call($command, array $parameters = array());

	public function queue($command, array $parameters = array());

	public function all();

	public function output();
}


?>
