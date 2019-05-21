<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Redis extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'redis';
	}
}

?>
