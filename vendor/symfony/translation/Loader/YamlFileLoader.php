<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

class YamlFileLoader extends FileLoader
{
	private $yamlParser;

	protected function loadResource($resource)
	{
		if (null === $this->yamlParser) {
			if (!class_exists('Symfony\\Component\\Yaml\\Parser')) {
				throw new \Symfony\Component\Translation\Exception\LogicException('Loading translations from the YAML format requires the Symfony Yaml component.');
			}

			$this->yamlParser = new \Symfony\Component\Yaml\Parser();
		}

		try {
			$messages = $this->yamlParser->parse(file_get_contents($resource), \Symfony\Component\Yaml\Yaml::PARSE_KEYS_AS_STRINGS);
		}
		catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException(sprintf('Error parsing YAML, invalid file "%s"', $resource), 0, $e);
		}

		return $messages;
	}
}

?>
