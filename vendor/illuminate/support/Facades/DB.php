<?php
//cgxlm
namespace Illuminate\Support\Facades;

class DB extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'db';
	}
}

?>
