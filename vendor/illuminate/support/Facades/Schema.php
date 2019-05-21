<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Schema extends Facade
{
	static public function connection($name)
	{
		return static::$app['db']->connection($name)->getSchemaBuilder();
	}

	static protected function getFacadeAccessor()
	{
		return static::$app['db']->connection()->getSchemaBuilder();
	}
}

?>
