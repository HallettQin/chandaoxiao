<?php
//cgxlm
namespace Illuminate\Contracts\View;

interface Factory
{
	public function exists($view);

	public function file($path, $data = array(), $mergeData = array());

	public function make($view, $data = array(), $mergeData = array());

	public function share($key, $value = NULL);

	public function composer($views, $callback);

	public function creator($views, $callback);

	public function addNamespace($namespace, $hints);

	public function replaceNamespace($namespace, $hints);
}


?>
