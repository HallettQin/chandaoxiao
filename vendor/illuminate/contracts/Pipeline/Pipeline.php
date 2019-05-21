<?php
//cgxlm
namespace Illuminate\Contracts\Pipeline;

interface Pipeline
{
	public function send($traveler);

	public function through($stops);

	public function via($method);

	public function then(\Closure $destination);
}


?>
