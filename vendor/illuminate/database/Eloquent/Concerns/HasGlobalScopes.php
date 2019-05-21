<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait HasGlobalScopes
{
	static public function addGlobalScope($scope, \Closure $implementation = NULL)
	{
		if (is_string($scope) && !is_null($implementation)) {
			return static::$globalScopes[static::class][$scope] = $implementation;
		}
		else if ($scope instanceof \Closure) {
			return static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
		}
		else if ($scope instanceof \Illuminate\Database\Eloquent\Scope) {
			return static::$globalScopes[static::class][get_class($scope)] = $scope;
		}

		throw new \InvalidArgumentException('Global scope must be an instance of Closure or Scope.');
	}

	static public function hasGlobalScope($scope)
	{
		return !is_null(static::getGlobalScope($scope));
	}

	static public function getGlobalScope($scope)
	{
		if (is_string($scope)) {
			return \Illuminate\Support\Arr::get(static::$globalScopes, static::class . '.' . $scope);
		}

		return \Illuminate\Support\Arr::get(static::$globalScopes, static::class . '.' . get_class($scope));
	}

	public function getGlobalScopes()
	{
		return \Illuminate\Support\Arr::get(static::$globalScopes, static::class, array());
	}
}


?>
