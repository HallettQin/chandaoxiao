<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Log extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'Psr\\Log\\LoggerInterface';
	}
}

?>
