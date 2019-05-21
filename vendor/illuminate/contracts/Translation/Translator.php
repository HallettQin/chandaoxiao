<?php
//cgxlm
namespace Illuminate\Contracts\Translation;

interface Translator
{
	public function trans($key, array $replace = array(), $locale = NULL);

	public function transChoice($key, $number, array $replace = array(), $locale = NULL);

	public function getLocale();

	public function setLocale($locale);
}


?>
