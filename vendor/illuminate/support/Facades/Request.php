<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Request extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'request';
	}
}

?>
