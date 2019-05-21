<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Queue extends Facade
{
	static public function fake()
	{
		static::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(static::getFacadeApplication()));
	}

	static protected function getFacadeAccessor()
	{
		return 'queue';
	}
}

?>
