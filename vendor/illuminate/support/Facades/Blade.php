<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Blade extends Facade
{
	static protected function getFacadeAccessor()
	{
		return static::$app['view']->getEngineResolver()->resolve('blade')->getCompiler();
	}
}

?>
