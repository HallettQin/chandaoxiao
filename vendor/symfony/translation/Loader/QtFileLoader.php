<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

class QtFileLoader implements LoaderInterface
{
	public function load($resource, $locale, $domain = 'messages')
	{
		if (!stream_is_local($resource)) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
		}

		if (!file_exists($resource)) {
			throw new \Symfony\Component\Translation\Exception\NotFoundResourceException(sprintf('File "%s" not found.', $resource));
		}

		try {
			$dom = \Symfony\Component\Config\Util\XmlUtils::loadFile($resource);
		}
		catch (\InvalidArgumentException $e) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('Unable to load "%s".', $resource), $e->getCode(), $e);
		}

		$internalErrors = libxml_use_internal_errors(true);
		libxml_clear_errors();
		$xpath = new \DOMXPath($dom);
		$nodes = $xpath->evaluate('//TS/context/name[text()="' . $domain . '"]');
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue($locale);

		if ($nodes->length == 1) {
			$translations = $nodes->item(0)->nextSibling->parentNode->parentNode->getElementsByTagName('message');

			foreach ($translations as $translation) {
				$translationValue = (string) $translation->getElementsByTagName('translation')->item(0)->nodeValue;

				if (!empty($translationValue)) {
					$catalogue->set((string) $translation->getElementsByTagName('source')->item(0)->nodeValue, $translationValue, $domain);
				}

				$translation = $translation->nextSibling;
			}

			if (class_exists('Symfony\\Component\\Config\\Resource\\FileResource')) {
				$catalogue->addResource(new \Symfony\Component\Config\Resource\FileResource($resource));
			}
		}

		libxml_use_internal_errors($internalErrors);
		return $catalogue;
	}
}

?>
