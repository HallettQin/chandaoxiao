<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Mail extends Facade
{
	static public function fake()
	{
		static::swap(new \Illuminate\Support\Testing\Fakes\MailFake());
	}

	static protected function getFacadeAccessor()
	{
		return 'mailer';
	}
}

?>
