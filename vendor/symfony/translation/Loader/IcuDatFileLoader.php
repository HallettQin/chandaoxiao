<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

class IcuDatFileLoader extends IcuResFileLoader
{
	public function load($resource, $locale, $domain = 'messages')
	{
		if (!stream_is_local($resource . '.dat')) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
		}

		if (!file_exists($resource . '.dat')) {
			throw new \Symfony\Component\Translation\Exception\NotFoundResourceException(sprintf('File "%s" not found.', $resource));
		}

		try {
			$rb = new \ResourceBundle($locale, $resource);
		}
		catch (\Exception $e) {
			$rb = null;
		}

		if (!$rb) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('Cannot load resource "%s"', $resource));
		}
		else if (intl_is_failure($rb->getErrorCode())) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException($rb->getErrorMessage(), $rb->getErrorCode());
		}

		$messages = $this->flatten($rb);
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue($locale);
		$catalogue->add($messages, $domain);

		if (class_exists('Symfony\\Component\\Config\\Resource\\FileResource')) {
			$catalogue->addResource(new \Symfony\Component\Config\Resource\FileResource($resource . '.dat'));
		}

		return $catalogue;
	}
}

?>
