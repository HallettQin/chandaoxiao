<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Cookie extends Facade
{
	static public function has($key)
	{
		return !is_null(static::$app['request']->cookie($key, null));
	}

	static public function get($key = NULL, $default = NULL)
	{
		return static::$app['request']->cookie($key, $default);
	}

	static protected function getFacadeAccessor()
	{
		return 'cookie';
	}
}

?>
