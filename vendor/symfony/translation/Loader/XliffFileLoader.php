<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

class XliffFileLoader implements LoaderInterface
{
	public function load($resource, $locale, $domain = 'messages')
	{
		if (!stream_is_local($resource)) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
		}

		if (!file_exists($resource)) {
			throw new \Symfony\Component\Translation\Exception\NotFoundResourceException(sprintf('File "%s" not found.', $resource));
		}

		$catalogue = new \Symfony\Component\Translation\MessageCatalogue($locale);
		$this->extract($resource, $catalogue, $domain);

		if (class_exists('Symfony\\Component\\Config\\Resource\\FileResource')) {
			$catalogue->addResource(new \Symfony\Component\Config\Resource\FileResource($resource));
		}

		return $catalogue;
	}

	private function extract($resource, \Symfony\Component\Translation\MessageCatalogue $catalogue, $domain)
	{
		try {
			$dom = \Symfony\Component\Config\Util\XmlUtils::loadFile($resource);
		}
		catch (\InvalidArgumentException $e) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('Unable to load "%s": %s', $resource, $e->getMessage()), $e->getCode(), $e);
		}

		$xliffVersion = $this->getVersionNumber($dom);
		$this->validateSchema($xliffVersion, $dom, $this->getSchema($xliffVersion));

		if ('1.2' === $xliffVersion) {
			$this->extractXliff1($dom, $catalogue, $domain);
		}

		if ('2.0' === $xliffVersion) {
			$this->extractXliff2($dom, $catalogue, $domain);
		}
	}

	private function extractXliff1(\DOMDocument $dom, \Symfony\Component\Translation\MessageCatalogue $catalogue, $domain)
	{
		$xml = simplexml_import_dom($dom);
		$encoding = strtoupper($dom->encoding);
		$xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

		foreach ($xml->xpath('//xliff:trans-unit') as $translation) {
			$attributes = $translation->attributes();
			if (!(isset($attributes['resname']) || isset($translation->source))) {
				continue;
			}

			$source = (isset($attributes['resname']) && $attributes['resname'] ? $attributes['resname'] : $translation->source);
			$target = $this->utf8ToCharset((string) (isset($translation->target) ? $translation->target : $source), $encoding);
			$catalogue->set((string) $source, $target, $domain);
			$metadata = array();

			if ($notes = $this->parseNotesMetadata($translation->note, $encoding)) {
				$metadata['notes'] = $notes;
			}

			if (isset($translation->target) && $translation->target->attributes()) {
				$metadata['target-attributes'] = array();

				foreach ($translation->target->attributes() as $key => $value) {
					$metadata['target-attributes'][$key] = (string) $value;
				}
			}

			if (isset($attributes['id'])) {
				$metadata['id'] = (string) $attributes['id'];
			}

			$catalogue->setMetadata((string) $source, $metadata, $domain);
		}
	}

	private function extractXliff2(\DOMDocument $dom, \Symfony\Component\Translation\MessageCatalogue $catalogue, $domain)
	{
		$xml = simplexml_import_dom($dom);
		$encoding = strtoupper($dom->encoding);
		$xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:2.0');

		foreach ($xml->xpath('//xliff:unit/xliff:segment') as $segment) {
			$source = $segment->source;
			$target = $this->utf8ToCharset((string) (isset($segment->target) ? $segment->target : $source), $encoding);
			$catalogue->set((string) $source, $target, $domain);
			$metadata = array();
			if (isset($segment->target) && $segment->target->attributes()) {
				$metadata['target-attributes'] = array();

				foreach ($segment->target->attributes() as $key => $value) {
					$metadata['target-attributes'][$key] = (string) $value;
				}
			}

			$catalogue->setMetadata((string) $source, $metadata, $domain);
		}
	}

	private function utf8ToCharset($content, $encoding = NULL)
	{
		if (('UTF-8' !== $encoding) && !empty($encoding)) {
			return mb_convert_encoding($content, $encoding, 'UTF-8');
		}

		return $content;
	}

	private function validateSchema($file, \DOMDocument $dom, $schema)
	{
		$internalErrors = libxml_use_internal_errors(true);
		$disableEntities = libxml_disable_entity_loader(false);

		if (!@$dom->schemaValidateSource($schema)) {
			libxml_disable_entity_loader($disableEntities);
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('Invalid resource provided: "%s"; Errors: %s', $file, implode("\n", $this->getXmlErrors($internalErrors))));
		}

		libxml_disable_entity_loader($disableEntities);
		$dom->normalizeDocument();
		libxml_clear_errors();
		libxml_use_internal_errors($internalErrors);
	}

	private function getSchema($xliffVersion)
	{
		if ('1.2' === $xliffVersion) {
			$schemaSource = file_get_contents(__DIR__ . '/schema/dic/xliff-core/xliff-core-1.2-strict.xsd');
			$xmlUri = 'http://www.w3.org/2001/xml.xsd';
		}
		else if ('2.0' === $xliffVersion) {
			$schemaSource = file_get_contents(__DIR__ . '/schema/dic/xliff-core/xliff-core-2.0.xsd');
			$xmlUri = 'informativeCopiesOf3rdPartySchemas/w3c/xml.xsd';
		}
		else {
			throw new \Symfony\Component\Translation\Exception\InvalidArgumentException(sprintf('No support implemented for loading XLIFF version "%s".', $xliffVersion));
		}

		return $this->fixXmlLocation($schemaSource, $xmlUri);
	}

	private function fixXmlLocation($schemaSource, $xmlUri)
	{
		$newPath = str_replace('\\', '/', __DIR__) . '/schema/dic/xliff-core/xml.xsd';
		$parts = explode('/', $newPath);

		if (0 === stripos($newPath, 'phar://')) {
			$tmpfile = tempnam(sys_get_temp_dir(), 'sf2');

			if ($tmpfile) {
				copy($newPath, $tmpfile);
				$parts = explode('/', str_replace('\\', '/', $tmpfile));
			}
		}

		$drive = ('\\' === DIRECTORY_SEPARATOR ? array_shift($parts) . '/' : '');
		$newPath = 'file:///' . $drive . implode('/', array_map('rawurlencode', $parts));
		return str_replace($xmlUri, $newPath, $schemaSource);
	}

	private function getXmlErrors($internalErrors)
	{
		$errors = array();

		foreach (libxml_get_errors() as $error) {
			$errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)', LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR', $error->code, trim($error->message), $error->file ?: 'n/a', $error->line, $error->column);
		}

		libxml_clear_errors();
		libxml_use_internal_errors($internalErrors);
		return $errors;
	}

	private function getVersionNumber(\DOMDocument $dom)
	{
		foreach ($dom->getElementsByTagName('xliff') as $xliff) {
			$version = $xliff->attributes->getNamedItem('version');

			if ($version) {
				return $version->nodeValue;
			}

			$namespace = $xliff->attributes->getNamedItem('xmlns');

			if ($namespace) {
				if (substr_compare('urn:oasis:names:tc:xliff:document:', $namespace->nodeValue, 0, 34) !== 0) {
					throw new \Symfony\Component\Translation\Exception\InvalidArgumentException(sprintf('Not a valid XLIFF namespace "%s"', $namespace));
				}

				return substr($namespace, 34);
			}
		}

		return '1.2';
	}

	private function parseNotesMetadata(\SimpleXMLElement $noteElement = NULL, $encoding = NULL)
	{
		$notes = array();

		if (null === $noteElement) {
			return $notes;
		}

		foreach ($noteElement as $xmlNote) {
			$noteAttributes = $xmlNote->attributes();
			$note = array('content' => $this->utf8ToCharset((string) $xmlNote, $encoding));

			if (isset($noteAttributes['priority'])) {
				$note['priority'] = (int) $noteAttributes['priority'];
			}

			if (isset($noteAttributes['from'])) {
				$note['from'] = (string) $noteAttributes['from'];
			}

			$notes[] = $note;
		}

		return $notes;
	}
}

?>
