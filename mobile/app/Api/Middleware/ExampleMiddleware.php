<?php
//cgxlm
namespace App\Api\Middleware;

class ExampleMiddleware
{
	public function handle($request, \Closure $next)
	{
		return $next($request);
	}
}


?>
