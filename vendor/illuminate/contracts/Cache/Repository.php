<?php
//cgxlm
namespace Illuminate\Contracts\Cache;

interface Repository
{
	public function has($key);

	public function get($key, $default = NULL);

	public function pull($key, $default = NULL);

	public function put($key, $value, $minutes);

	public function add($key, $value, $minutes);

	public function increment($key, $value = 1);

	public function decrement($key, $value = 1);

	public function forever($key, $value);

	public function remember($key, $minutes, \Closure $callback);

	public function sear($key, \Closure $callback);

	public function rememberForever($key, \Closure $callback);

	public function forget($key);
}


?>
