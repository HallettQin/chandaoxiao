<?php
//cgxlm
if (!function_exists('append_config')) {
	function append_config(array $array)
	{
		$start = 9999;

		foreach ($array as $key => $value) {
			if (is_numeric($key)) {
				$start++;
				$array[$start] = \Illuminate\Support\Arr::pull($array, $key);
			}
		}

		return $array;
	}
}

if (!function_exists('array_add')) {
	function array_add($array, $key, $value)
	{
		return \Illuminate\Support\Arr::add($array, $key, $value);
	}
}

if (!function_exists('array_collapse')) {
	function array_collapse($array)
	{
		return \Illuminate\Support\Arr::collapse($array);
	}
}

if (!function_exists('array_divide')) {
	function array_divide($array)
	{
		return \Illuminate\Support\Arr::divide($array);
	}
}

if (!function_exists('array_dot')) {
	function array_dot($array, $prepend = '')
	{
		return \Illuminate\Support\Arr::dot($array, $prepend);
	}
}

if (!function_exists('array_except')) {
	function array_except($array, $keys)
	{
		return \Illuminate\Support\Arr::except($array, $keys);
	}
}

if (!function_exists('array_first')) {
	function array_first($array,  $callback = NULL, $default = NULL)
	{
		return \Illuminate\Support\Arr::first($array, $callback, $default);
	}
}

if (!function_exists('array_flatten')) {
	function array_flatten($array, $depth = INF)
	{
		return \Illuminate\Support\Arr::flatten($array, $depth);
	}
}

if (!function_exists('array_forget')) {
	function array_forget(&$array, $keys)
	{
		return \Illuminate\Support\Arr::forget($array, $keys);
	}
}

if (!function_exists('array_get')) {
	function array_get($array, $key, $default = NULL)
	{
		return \Illuminate\Support\Arr::get($array, $key, $default);
	}
}

if (!function_exists('array_has')) {
	function array_has($array, $keys)
	{
		return \Illuminate\Support\Arr::has($array, $keys);
	}
}

if (!function_exists('array_last')) {
	function array_last($array,  $callback = NULL, $default = NULL)
	{
		return \Illuminate\Support\Arr::last($array, $callback, $default);
	}
}

if (!function_exists('array_only')) {
	function array_only($array, $keys)
	{
		return \Illuminate\Support\Arr::only($array, $keys);
	}
}

if (!function_exists('array_pluck')) {
	function array_pluck($array, $value, $key = NULL)
	{
		return \Illuminate\Support\Arr::pluck($array, $value, $key);
	}
}

if (!function_exists('array_prepend')) {
	function array_prepend($array, $value, $key = NULL)
	{
		return \Illuminate\Support\Arr::prepend($array, $value, $key);
	}
}

if (!function_exists('array_pull')) {
	function array_pull(&$array, $key, $default = NULL)
	{
		return \Illuminate\Support\Arr::pull($array, $key, $default);
	}
}

if (!function_exists('array_set')) {
	function array_set(&$array, $key, $value)
	{
		return \Illuminate\Support\Arr::set($array, $key, $value);
	}
}

if (!function_exists('array_sort')) {
	function array_sort($array, $callback)
	{
		return \Illuminate\Support\Arr::sort($array, $callback);
	}
}

if (!function_exists('array_sort_recursive')) {
	function array_sort_recursive($array)
	{
		return \Illuminate\Support\Arr::sortRecursive($array);
	}
}

if (!function_exists('array_where')) {
	function array_where($array,  $callback)
	{
		return \Illuminate\Support\Arr::where($array, $callback);
	}
}

if (!function_exists('array_wrap')) {
	function array_wrap($value)
	{
		return \Illuminate\Support\Arr::wrap($value);
	}
}

if (!function_exists('camel_case')) {
	function camel_case($value)
	{
		return \Illuminate\Support\Str::camel($value);
	}
}

if (!function_exists('class_basename')) {
	function class_basename($class)
	{
		$class = (is_object($class) ? get_class($class) : $class);
		return basename(str_replace('\\', '/', $class));
	}
}

if (!function_exists('class_uses_recursive')) {
	function class_uses_recursive($class)
	{
		if (is_object($class)) {
			$class = get_class($class);
		}

		$results = array();

		foreach (array_merge(array($class => $class), class_parents($class)) as $class) {
			$results += trait_uses_recursive($class);
		}

		return array_unique($results);
	}
}

if (!function_exists('collect')) {
	function collect($value = NULL)
	{
		return new \Illuminate\Support\Collection($value);
	}
}

if (!function_exists('data_fill')) {
	function data_fill(&$target, $key, $value)
	{
		return data_set($target, $key, $value, false);
	}
}

if (!function_exists('data_get')) {
	function data_get($target, $key, $default = NULL)
	{
		if (is_null($key)) {
			return $target;
		}

		$key = (is_array($key) ? $key : explode('.', $key));

		while (!is_null($segment = array_shift($key))) {
			if ($segment === '*') {
				if ($target instanceof \Illuminate\Support\Collection) {
					$target = $target->all();
				}
				else if (!is_array($target)) {
					return value($default);
				}

				$result = \Illuminate\Support\Arr::pluck($target, $key);
				return in_array('*', $key) ? \Illuminate\Support\Arr::collapse($result) : $result;
			}

			if (\Illuminate\Support\Arr::accessible($target) && \Illuminate\Support\Arr::exists($target, $segment)) {
				$target = $target[$segment];
			}
			else {
				if (is_object($target) && isset($target->$segment)) {
					$target = $target->$segment;
				}
				else {
					return value($default);
				}
			}
		}

		return $target;
	}
}

if (!function_exists('data_set')) {
	function data_set(&$target, $key, $value, $overwrite = true)
	{
		$segments = (is_array($key) ? $key : explode('.', $key));

		if (($segment = array_shift($segments)) === '*') {
			if (!\Illuminate\Support\Arr::accessible($target)) {
				$target = array();
			}

			if ($segments) {
				foreach ($target as &$inner) {
					data_set($inner, $segments, $value, $overwrite);
				}
			}
			else if ($overwrite) {
				foreach ($target as &$inner) {
					$inner = $value;
				}
			}
		}
		else if (\Illuminate\Support\Arr::accessible($target)) {
			if ($segments) {
				if (!\Illuminate\Support\Arr::exists($target, $segment)) {
					$target[$segment] = array();
				}

				data_set($target[$segment], $segments, $value, $overwrite);
			}
			else {
				if ($overwrite || !\Illuminate\Support\Arr::exists($target, $segment)) {
					$target[$segment] = $value;
				}
			}
		}
		else if (is_object($target)) {
			if ($segments) {
				if (!isset($target->$segment)) {
					$target->$segment = array();
				}

				data_set($target->$segment, $segments, $value, $overwrite);
			}
			else {
				if ($overwrite || !isset($target->$segment)) {
					$target->$segment = $value;
				}
			}
		}
		else {
			$target = array();

			if ($segments) {
				data_set($target[$segment], $segments, $value, $overwrite);
			}
			else if ($overwrite) {
				$target[$segment] = $value;
			}
		}

		return $target;
	}
}

if (!function_exists('dd')) {
	function dd(...$args)
	{
		foreach ($args as $x) {
			(new \Illuminate\Support\Debug\Dumper())->dump($x);
		}

		exit(1);
	}
}

if (!function_exists('e')) {
	function e($value)
	{
		if ($value instanceof \Illuminate\Contracts\Support\Htmlable) {
			return $value->toHtml();
		}

		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
	}
}

if (!function_exists('ends_with')) {
	function ends_with($haystack, $needles)
	{
		return \Illuminate\Support\Str::endsWith($haystack, $needles);
	}
}

if (!function_exists('env')) {
	function env($key, $default = NULL)
	{
		$value = getenv($key);

		if ($value === false) {
			return value($default);
		}

		switch (strtolower($value)) {
		case 'true':
		case '(true)':
			return true;
		case 'false':
		case '(false)':
			return false;
		case 'empty':
		case '(empty)':
			return '';
		case 'null':
		case '(null)':
			return NULL;
		}

		if ((1 < strlen($value)) && \Illuminate\Support\Str::startsWith($value, '"') && \Illuminate\Support\Str::endsWith($value, '"')) {
			return substr($value, 1, -1);
		}

		return $value;
	}
}

if (!function_exists('head')) {
	function head($array)
	{
		return reset($array);
	}
}

if (!function_exists('kebab_case')) {
	function kebab_case($value)
	{
		return \Illuminate\Support\Str::kebab($value);
	}
}

if (!function_exists('last')) {
	function last($array)
	{
		return end($array);
	}
}

if (!function_exists('object_get')) {
	function object_get($object, $key, $default = NULL)
	{
		if (is_null($key) || (trim($key) == '')) {
			return $object;
		}

		foreach (explode('.', $key) as $segment) {
			if (!is_object($object) || !isset($object->$segment)) {
				return value($default);
			}

			$object = $object->$segment;
		}

		return $object;
	}
}

if (!function_exists('preg_replace_array')) {
	function preg_replace_array($pattern, array $replacements, $subject)
	{
		return preg_replace_callback($pattern, function() use(&$replacements) {
			foreach ($replacements as $key => $value) {
				return array_shift($replacements);
			}
		}, $subject);
	}
}

if (!function_exists('retry')) {
	function retry($times,  $callback, $sleep = 0)
	{
		$times--;

		try {
			return $callback();
		}
		catch (Exception $e) {
			if (!$times) {
				throw $e;
			}

			$times--;

			if ($sleep) {
				usleep($sleep * 1000);
			}
		}
	}
}

if (!function_exists('snake_case')) {
	function snake_case($value, $delimiter = '_')
	{
		return \Illuminate\Support\Str::snake($value, $delimiter);
	}
}

if (!function_exists('starts_with')) {
	function starts_with($haystack, $needles)
	{
		return \Illuminate\Support\Str::startsWith($haystack, $needles);
	}
}

if (!function_exists('str_after')) {
	function str_after($subject, $search)
	{
		return \Illuminate\Support\Str::after($subject, $search);
	}
}

if (!function_exists('str_contains')) {
	function str_contains($haystack, $needles)
	{
		return \Illuminate\Support\Str::contains($haystack, $needles);
	}
}

if (!function_exists('str_finish')) {
	function str_finish($value, $cap)
	{
		return \Illuminate\Support\Str::finish($value, $cap);
	}
}

if (!function_exists('str_is')) {
	function str_is($pattern, $value)
	{
		return \Illuminate\Support\Str::is($pattern, $value);
	}
}

if (!function_exists('str_limit')) {
	function str_limit($value, $limit = 100, $end = '...')
	{
		return \Illuminate\Support\Str::limit($value, $limit, $end);
	}
}

if (!function_exists('str_plural')) {
	function str_plural($value, $count = 2)
	{
		return \Illuminate\Support\Str::plural($value, $count);
	}
}

if (!function_exists('str_random')) {
	function str_random($length = 16)
	{
		return \Illuminate\Support\Str::random($length);
	}
}

if (!function_exists('str_replace_array')) {
	function str_replace_array($search, array $replace, $subject)
	{
		return \Illuminate\Support\Str::replaceArray($search, $replace, $subject);
	}
}

if (!function_exists('str_replace_first')) {
	function str_replace_first($search, $replace, $subject)
	{
		return \Illuminate\Support\Str::replaceFirst($search, $replace, $subject);
	}
}

if (!function_exists('str_replace_last')) {
	function str_replace_last($search, $replace, $subject)
	{
		return \Illuminate\Support\Str::replaceLast($search, $replace, $subject);
	}
}

if (!function_exists('str_singular')) {
	function str_singular($value)
	{
		return \Illuminate\Support\Str::singular($value);
	}
}

if (!function_exists('str_slug')) {
	function str_slug($title, $separator = '-')
	{
		return \Illuminate\Support\Str::slug($title, $separator);
	}
}

if (!function_exists('studly_case')) {
	function studly_case($value)
	{
		return \Illuminate\Support\Str::studly($value);
	}
}

if (!function_exists('tap')) {
	function tap($value, $callback = NULL)
	{
		if (is_null($callback)) {
			return new \Illuminate\Support\HigherOrderTapProxy($value);
		}

		$callback($value);
		return $value;
	}
}

if (!function_exists('title_case')) {
	function title_case($value)
	{
		return \Illuminate\Support\Str::title($value);
	}
}

if (!function_exists('trait_uses_recursive')) {
	function trait_uses_recursive($trait)
	{
		$traits = class_uses($trait);

		foreach ($traits as $trait) {
			$traits += trait_uses_recursive($trait);
		}

		return $traits;
	}
}

if (!function_exists('value')) {
	function value($value)
	{
		return $value instanceof Closure ? $value() : $value;
	}
}

if (!function_exists('windows_os')) {
	function windows_os()
	{
		return strtolower(substr(PHP_OS, 0, 3)) === 'win';
	}
}

if (!function_exists('with')) {
	function with($object)
	{
		return $object;
	}
}

?>
