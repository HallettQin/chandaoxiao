<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

abstract class FileLoader extends ArrayLoader
{
	public function load($resource, $locale, $domain = 'messages')
	{
		if (!stream_is_local($resource)) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
		}

		if (!file_exists($resource)) {
			throw new \Symfony\Component\Translation\Exception\NotFoundResourceException(sprintf('File "%s" not found.', $resource));
		}

		$messages = $this->loadResource($resource);

		if (null === $messages) {
			$messages = array();
		}

		if (!is_array($messages)) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('Unable to load file "%s".', $resource));
		}

		$catalogue = parent::load($messages, $locale, $domain);

		if (class_exists('Symfony\\Component\\Config\\Resource\\FileResource')) {
			$catalogue->addResource(new \Symfony\Component\Config\Resource\FileResource($resource));
		}

		return $catalogue;
	}

	abstract protected function loadResource($resource);
}

?>
