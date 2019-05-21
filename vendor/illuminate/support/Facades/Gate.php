<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Gate extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'Illuminate\\Contracts\\Auth\\Access\\Gate';
	}
}

?>
