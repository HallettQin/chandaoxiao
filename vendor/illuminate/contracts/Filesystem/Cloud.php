<?php
//cgxlm
namespace Illuminate\Contracts\Filesystem;

interface Cloud extends Filesystem
{
	public function url($path);
}

?>
