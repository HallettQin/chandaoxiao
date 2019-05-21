<?php
//cgxlm
namespace Illuminate\Support;

class Arr
{
	use Traits\Macroable;

	static public function accessible($value)
	{
		return is_array($value) || $value instanceof \ArrayAccess;
	}

	static public function add($array, $key, $value)
	{
		if (is_null(static::get($array, $key))) {
			static::set($array, $key, $value);
		}

		return $array;
	}

	static public function collapse($array)
	{
		$results = array();

		foreach ($array as $values) {
			if ($values instanceof Collection) {
				$values = $values->all();
			}
			else if (!is_array($values)) {
				continue;
			}

			$results = array_merge($results, $values);
		}

		return $results;
	}

	static public function crossJoin(...$arrays)
	{
		return array_reduce($arrays, function($results, $array) {
			return static::collapse(array_map(function($parent) use($array) {
				return array_map(function($item) use($parent) {
					return array_merge($parent, array($item));
				}, $array);
			}, $results));
		}, array(
	array()
	));
	}

	static public function divide($array)
	{
		return array(array_keys($array), array_values($array));
	}

	static public function dot($array, $prepend = '')
	{
		$results = array();

		foreach ($array as $key => $value) {
			if (is_array($value) && !empty($value)) {
				$results = array_merge($results, static::dot($value, $prepend . $key . '.'));
			}
			else {
				$results[$prepend . $key] = $value;
			}
		}

		return $results;
	}

	static public function except($array, $keys)
	{
		static::forget($array, $keys);
		return $array;
	}

	static public function exists($array, $key)
	{
		if ($array instanceof \ArrayAccess) {
			return $array->offsetExists($key);
		}

		return array_key_exists($key, $array);
	}

	static public function first($array,  $callback = NULL, $default = NULL)
	{
		if (is_null($callback)) {
			if (empty($array)) {
				return value($default);
			}

			foreach ($array as $item) {
				return $item;
			}
		}

		foreach ($array as $key => $value) {
			if (call_user_func($callback, $value, $key)) {
				return $value;
			}
		}

		return value($default);
	}

	static public function last($array,  $callback = NULL, $default = NULL)
	{
		if (is_null($callback)) {
			return empty($array) ? value($default) : end($array);
		}

		return static::first(array_reverse($array, true), $callback, $default);
	}

	static public function flatten($array, $depth = INF)
	{
		return array_reduce($array, function($result, $item) use($depth) {
			$item = ($item instanceof Collection ? $item->all() : $item);

			if (!is_array($item)) {
				return array_merge($result, array($item));
			}
			else if ($depth === 1) {
				return array_merge($result, array_values($item));
			}
			else {
				return array_merge($result, static::flatten($item, $depth - 1));
			}
		}, array());
	}

	static public function forget(&$array, $keys)
	{
		$original = &$array;
		$keys = (array) $keys;

		if (count($keys) === 0) {
			return NULL;
		}

		foreach ($keys as $key) {
			if (static::exists($array, $key)) {
				unset($array[$key]);
				continue;
			}

			$parts = explode('.', $key);
			$array = &$original;

			while (1 < count($parts)) {
				$part = array_shift($parts);
				if (isset($array[$part]) && is_array($array[$part])) {
					$array = &$array[$part];
				}
				else {
					continue 2;
				}
			}

			unset($array[array_shift($parts)]);
		}
	}

	static public function get($array, $key, $default = NULL)
	{
		if (!static::accessible($array)) {
			return value($default);
		}

		if (is_null($key)) {
			return $array;
		}

		if (static::exists($array, $key)) {
			return $array[$key];
		}

		foreach (explode('.', $key) as $segment) {
			if (static::accessible($array) && static::exists($array, $segment)) {
				$array = $array[$segment];
			}
			else {
				return value($default);
			}
		}

		return $array;
	}

	static public function has($array, $keys)
	{
		if (is_null($keys)) {
			return false;
		}

		$keys = (array) $keys;

		if (!$array) {
			return false;
		}

		if ($keys === array()) {
			return false;
		}

		foreach ($keys as $key) {
			$subKeyArray = $array;

			if (static::exists($array, $key)) {
				continue;
			}

			foreach (explode('.', $key) as $segment) {
				if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
					$subKeyArray = $subKeyArray[$segment];
				}
				else {
					return false;
				}
			}
		}

		return true;
	}

	static public function isAssoc(array $array)
	{
		$keys = array_keys($array);
		return array_keys($keys) !== $keys;
	}

	static public function only($array, $keys)
	{
		return array_intersect_key($array, array_flip((array) $keys));
	}

	static public function pluck($array, $value, $key = NULL)
	{
		$results = array();
		list($value, $key) = static::explodePluckParameters($value, $key);

		foreach ($array as $item) {
			$itemValue = data_get($item, $value);

			if (is_null($key)) {
				$results[] = $itemValue;
			}
			else {
				$itemKey = data_get($item, $key);
				$results[$itemKey] = $itemValue;
			}
		}

		return $results;
	}

	static protected function explodePluckParameters($value, $key)
	{
		$value = (is_string($value) ? explode('.', $value) : $value);
		$key = (is_null($key) || is_array($key) ? $key : explode('.', $key));
		return array($value, $key);
	}

	static public function prepend($array, $value, $key = NULL)
	{
		if (is_null($key)) {
			array_unshift($array, $value);
		}
		else {
			$array = array($key => $value) + $array;
		}

		return $array;
	}

	static public function pull(&$array, $key, $default = NULL)
	{
		$value = static::get($array, $key, $default);
		static::forget($array, $key);
		return $value;
	}

	static public function set(&$array, $key, $value)
	{
		if (is_null($key)) {
			return $array = $value;
		}

		$keys = explode('.', $key);

		while (1 < count($keys)) {
			$key = array_shift($keys);
			if (!isset($array[$key]) || !is_array($array[$key])) {
				$array[$key] = array();
			}

			$array = &$array[$key];
		}

		$array[array_shift($keys)] = $value;
		return $array;
	}

	static public function shuffle($array)
	{
		shuffle($array);
		return $array;
	}

	static public function sort($array, $callback)
	{
		return Collection::make($array)->sortBy($callback)->all();
	}

	static public function sortRecursive($array)
	{
		foreach ($array as &$value) {
			if (is_array($value)) {
				$value = static::sortRecursive($value);
			}
		}

		if (static::isAssoc($array)) {
			ksort($array);
		}
		else {
			sort($array);
		}

		return $array;
	}

	static public function where($array,  $callback)
	{
		return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
	}

	static public function wrap($value)
	{
		return !is_array($value) ? array($value) : $value;
	}
}

?>
