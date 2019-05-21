<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Artisan extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'Illuminate\\Contracts\\Console\\Kernel';
	}
}

?>
