<?php
//cgxlm
namespace Illuminate\Contracts\Container;

interface Container
{
	public function bound($abstract);

	public function alias($abstract, $alias);

	public function tag($abstracts, $tags);

	public function tagged($tag);

	public function bind($abstract, $concrete = NULL, $shared = false);

	public function bindIf($abstract, $concrete = NULL, $shared = false);

	public function singleton($abstract, $concrete = NULL);

	public function extend($abstract, \Closure $closure);

	public function instance($abstract, $instance);

	public function when($concrete);

	public function factory($abstract);

	public function make($abstract);

	public function call($callback, array $parameters = array(), $defaultMethod = NULL);

	public function resolved($abstract);

	public function resolving($abstract, \Closure $callback = NULL);

	public function afterResolving($abstract, \Closure $callback = NULL);
}


?>
