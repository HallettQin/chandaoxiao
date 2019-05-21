<?php
//cgxlm
namespace Illuminate\Support\Facades;

class Crypt extends Facade
{
	static protected function getFacadeAccessor()
	{
		return 'encrypter';
	}
}

?>
