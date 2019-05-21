<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Auth extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'auth';
	}

	static public function routes()
	{
		static::$app->make('router')->auth();
	}
}

?>
