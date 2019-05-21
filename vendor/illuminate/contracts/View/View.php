<?php
//cgxlm
namespace Illuminate\Contracts\View;

interface View extends \Illuminate\Contracts\Support\Renderable
{
	public function name();

	public function with($key, $value = NULL);
}

?>
