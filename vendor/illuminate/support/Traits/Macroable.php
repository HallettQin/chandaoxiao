<?php
//cgxlm
namespace Illuminate\Support\Traits;

trait Macroable
{
	/**
     * The registered string macros.
     *
     * @var array
     */
	static protected $macros = array();

	static public function macro($name,  $macro)
	{
		static::$macros[$name] = $macro;
	}

	static public function hasMacro($name)
	{
		return isset(static::$macros[$name]);
	}

	static public function __callStatic($method, $parameters)
	{
		if (!static::hasMacro($method)) {
			throw new \BadMethodCallException('Method ' . $method . ' does not exist.');
		}

		if (static::$macros[$method] instanceof \Closure) {
			return call_user_func_array(\Closure::bind(static::$macros[$method], null, static::class), $parameters);
		}

		return call_user_func_array(static::$macros[$method], $parameters);
	}

	public function __call($method, $parameters)
	{
		if (!static::hasMacro($method)) {
			throw new \BadMethodCallException('Method ' . $method . ' does not exist.');
		}

		if (static::$macros[$method] instanceof \Closure) {
			return call_user_func_array(static::$macros[$method]->bindTo($this, static::class), $parameters);
		}

		return call_user_func_array(static::$macros[$method], $parameters);
	}
}


?>
