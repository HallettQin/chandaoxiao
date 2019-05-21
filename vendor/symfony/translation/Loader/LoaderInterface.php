<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

interface LoaderInterface
{
	public function load($resource, $locale, $domain = 'messages');
}


?>
