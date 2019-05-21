<?php
//cgxlm
namespace Illuminate\Contracts\Cookie;

interface Factory
{
	public function make($name, $value, $minutes = 0, $path = NULL, $domain = NULL, $secure = false, $httpOnly = true);

	public function forever($name, $value, $path = NULL, $domain = NULL, $secure = false, $httpOnly = true);

	public function forget($name, $path = NULL, $domain = NULL);
}


?>
