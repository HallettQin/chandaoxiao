<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Response extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'Illuminate\\Contracts\\Routing\\ResponseFactory';
	}
}

?>
