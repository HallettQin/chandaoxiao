<?php
//cgxlm
namespace Illuminate\Contracts\Auth;

interface Factory
{
	public function guard($name = NULL);

	public function shouldUse($name);
}


?>
