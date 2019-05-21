<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Notification extends Facade
{
	static public function fake()
	{
		static::swap(new \Illuminate\Support\Testing\Fakes\NotificationFake());
	}

	static protected function getFacadeAccessor()
	{
		return 'Illuminate\\Notifications\\ChannelManager';
	}
}

?>
