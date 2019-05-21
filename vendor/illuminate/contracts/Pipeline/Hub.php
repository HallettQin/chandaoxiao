<?php
//cgxlm
namespace Illuminate\Contracts\Pipeline;

interface Hub
{
	public function pipe($object, $pipeline = NULL);
}


?>
