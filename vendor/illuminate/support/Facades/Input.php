<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Input extends Facade
{
	static public function get($key = NULL, $default = NULL)
	{
		return static::$app['request']->input($key, $default);
	}

	static protected function getFacadeAccessor()
	{
		return 'request';
	}
}

?>
