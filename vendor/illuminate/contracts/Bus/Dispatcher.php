<?php
//cgxlm
namespace Illuminate\Contracts\Bus;

interface Dispatcher
{
	public function dispatch($command);

	public function dispatchNow($command, $handler = NULL);

	public function pipeThrough(array $pipes);
}


?>
