<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Event extends Facade
{
	static public function fake()
	{
		static::swap($fake = new \Illuminate\Support\Testing\Fakes\EventFake());
		\Illuminate\Database\Eloquent\Model::setEventDispatcher($fake);
	}

	static protected function getFacadeAccessor()
	{
		return 'events';
	}
}

?>
