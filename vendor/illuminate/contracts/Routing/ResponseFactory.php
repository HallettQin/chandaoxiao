<?php
//cgxlm
namespace Illuminate\Contracts\Routing;

interface ResponseFactory
{
	public function make($content = '', $status = 200, array $headers = array());

	public function view($view, $data = array(), $status = 200, array $headers = array());

	public function json($data = array(), $status = 200, array $headers = array(), $options = 0);

	public function jsonp($callback, $data = array(), $status = 200, array $headers = array(), $options = 0);

	public function stream($callback, $status = 200, array $headers = array());

	public function download($file, $name = NULL, array $headers = array(), $disposition = 'attachment');

	public function redirectTo($path, $status = 302, $headers = array(), $secure = NULL);

	public function redirectToRoute($route, $parameters = array(), $status = 302, $headers = array());

	public function redirectToAction($action, $parameters = array(), $status = 302, $headers = array());

	public function redirectGuest($path, $status = 302, $headers = array(), $secure = NULL);

	public function redirectToIntended($default = '/', $status = 302, $headers = array(), $secure = NULL);
}


?>
