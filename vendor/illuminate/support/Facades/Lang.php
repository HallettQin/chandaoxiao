<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Lang extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'translator';
	}
}

?>
