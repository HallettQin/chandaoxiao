<?php
//cgxlm
namespace Symfony\Component\Translation;

class IdentityTranslator implements TranslatorInterface
{
	private $selector;
	private $locale;

	public function __construct(MessageSelector $selector = NULL)
	{
		$this->selector = $selector ?: new MessageSelector();
	}

	public function setLocale($locale)
	{
		$this->locale = $locale;
	}

	public function getLocale()
	{
		return $this->locale ?: \Locale::getDefault();
	}

	public function trans($id, array $parameters = array(), $domain = NULL, $locale = NULL)
	{
		return strtr((string) $id, $parameters);
	}

	public function transChoice($id, $number, array $parameters = array(), $domain = NULL, $locale = NULL)
	{
		return strtr($this->selector->choose((string) $id, (int) $number, $locale ?: $this->getLocale()), $parameters);
	}
}

?>
