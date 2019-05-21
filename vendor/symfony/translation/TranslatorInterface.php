<?php
//cgxlm
namespace Symfony\Component\Translation;

interface TranslatorInterface
{
	public function trans($id, array $parameters = array(), $domain = NULL, $locale = NULL);

	public function transChoice($id, $number, array $parameters = array(), $domain = NULL, $locale = NULL);

	public function setLocale($locale);

	public function getLocale();
}


?>
