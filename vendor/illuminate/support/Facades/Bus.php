<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Bus extends Facade
{
	static public function fake()
	{
		static::swap(new \Illuminate\Support\Testing\Fakes\BusFake());
	}

	static protected function getFacadeAccessor()
	{
		return 'Illuminate\\Contracts\\Bus\\Dispatcher';
	}
}

?>
