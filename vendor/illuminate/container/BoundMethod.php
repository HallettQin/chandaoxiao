<?php
//cgxlm
namespace Illuminate\Container;

class BoundMethod
{
	static public function call($container, $callback, array $parameters = array(), $defaultMethod = NULL)
	{
		if (static::isCallableWithAtSign($callback) || $defaultMethod) {
			return static::callClass($container, $callback, $parameters, $defaultMethod);
		}

		return static::callBoundMethod($container, $callback, function() use($container, $callback, $parameters) {
			return call_user_func_array($callback, static::getMethodDependencies($container, $callback, $parameters));
		});
	}

	static protected function callClass($container, $target, array $parameters = array(), $defaultMethod = NULL)
	{
		$segments = explode('@', $target);
		$method = (count($segments) == 2 ? $segments[1] : $defaultMethod);

		if (is_null($method)) {
			throw new \InvalidArgumentException('Method not provided.');
		}

		return static::call($container, array($container->make($segments[0]), $method), $parameters);
	}

	static protected function callBoundMethod($container, $callback, $default)
	{
		if (!is_array($callback)) {
			return $default instanceof \Closure ? $default() : $default;
		}

		$method = static::normalizeMethod($callback);

		if ($container->hasMethodBinding($method)) {
			return $container->callMethodBinding($method, $callback[0]);
		}

		return $default instanceof \Closure ? $default() : $default;
	}

	static protected function normalizeMethod($callback)
	{
		$class = (is_string($callback[0]) ? $callback[0] : get_class($callback[0]));
		return $class . '@' . $callback[1];
	}

	static protected function getMethodDependencies($container, $callback, array $parameters = array())
	{
		$dependencies = array();

		foreach (static::getCallReflector($callback)->getParameters() as $parameter) {
			static::addDependencyForCallParameter($container, $parameter, $parameters, $dependencies);
		}

		return array_merge($dependencies, $parameters);
	}

	static protected function getCallReflector($callback)
	{
		if (is_string($callback) && (strpos($callback, '::') !== false)) {
			$callback = explode('::', $callback);
		}

		return is_array($callback) ? new \ReflectionMethod($callback[0], $callback[1]) : new \ReflectionFunction($callback);
	}

	static protected function addDependencyForCallParameter($container, $parameter, array &$parameters, &$dependencies)
	{
		if (array_key_exists($parameter->name, $parameters)) {
			$dependencies[] = $parameters[$parameter->name];
			unset($parameters[$parameter->name]);
		}
		else if ($parameter->getClass()) {
/* [31m * TODO SEPARATE[0m */
			$dependencies[] = $container->make($parameter->getClass()->name);
		}
		else if ($parameter->isDefaultValueAvailable()) {
			$dependencies[] = $parameter->getDefaultValue();
		}
	}

	static protected function isCallableWithAtSign($callback)
	{
		return is_string($callback) && (strpos($callback, '@') !== false);
	}
}


?>
