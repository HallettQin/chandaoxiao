<?php
//cgxlm
namespace Illuminate\Support\Facades;

abstract class Facade
{
	/**
     * The application instance being facaded.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
	static protected $app;
	/**
     * The resolved object instances.
     *
     * @var array
     */
	static protected $resolvedInstance;

	static public function spy()
	{
		if (!static::isMock()) {
			$class = static::getMockableClass();
			static::swap($class ? \Mockery::spy($class) : \Mockery::spy());
		}
	}

	static public function shouldReceive()
	{
		$name = static::getFacadeAccessor();
		$mock = (static::isMock() ? static::$resolvedInstance[$name] : static::createFreshMockInstance());
		return $mock->shouldReceive(...func_get_args());
	}

	static protected function createFreshMockInstance()
	{
		return tap(static::createMock(), function($mock) {
			static::swap($mock);
			$mock->shouldAllowMockingProtectedMethods();
		});
	}

	static protected function createMock()
	{
		$class = static::getMockableClass();
		return $class ? \Mockery::mock($class) : \Mockery::mock();
	}

	static protected function isMock()
	{
		$name = static::getFacadeAccessor();
		return isset(static::$resolvedInstance[$name]) && static::$resolvedInstance[$name] instanceof \Mockery\MockInterface;
	}

	static protected function getMockableClass()
	{
		if ($root = static::getFacadeRoot()) {
			return get_class($root);
		}
	}

	static public function swap($instance)
	{
		static::$resolvedInstance[static::getFacadeAccessor()] = $instance;

		if (isset(static::$app)) {
			static::$app->instance(static::getFacadeAccessor(), $instance);
		}
	}

	static public function getFacadeRoot()
	{
		return static::resolveFacadeInstance(static::getFacadeAccessor());
	}

	static protected function getFacadeAccessor()
	{
		throw new \RuntimeException('Facade does not implement getFacadeAccessor method.');
	}

	static protected function resolveFacadeInstance($name)
	{
		if (is_object($name)) {
			return $name;
		}

		if (isset(static::$resolvedInstance[$name])) {
			return static::$resolvedInstance[$name];
		}

		return static::$resolvedInstance[$name] = static::$app[$name];
	}

	static public function clearResolvedInstance($name)
	{
		unset(static::$resolvedInstance[$name]);
	}

	static public function clearResolvedInstances()
	{
		static::$resolvedInstance = array();
	}

	static public function getFacadeApplication()
	{
		return static::$app;
	}

	static public function setFacadeApplication($app)
	{
		static::$app = $app;
	}

	static public function __callStatic($method, $args)
	{
		$instance = static::getFacadeRoot();

		if (!$instance) {
			throw new \RuntimeException('A facade root has not been set.');
		}

		return $instance->$method(...$args);
	}
}


?>
