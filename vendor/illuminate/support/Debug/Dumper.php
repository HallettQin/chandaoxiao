<?php
//cgxlm
namespace Illuminate\Support\Debug;

class Dumper
{
	public function dump($value)
	{
		if (class_exists('Symfony\\Component\\VarDumper\\Dumper\\CliDumper')) {
			$dumper = ('cli' === PHP_SAPI ? new \Symfony\Component\VarDumper\Dumper\CliDumper() : new HtmlDumper());
			$dumper->dump((new \Symfony\Component\VarDumper\Cloner\VarCloner())->cloneVar($value));
		}
		else {
			var_dump($value);
		}
	}
}


?>
