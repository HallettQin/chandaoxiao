<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Broadcast extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'Illuminate\\Contracts\\Broadcasting\\Factory';
	}
}

?>
