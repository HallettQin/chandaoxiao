<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

class YamlFileDumper extends FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		if (!class_exists('Symfony\\Component\\Yaml\\Yaml')) {
			throw new \Symfony\Component\Translation\Exception\LogicException('Dumping translations in the YAML format requires the Symfony Yaml component.');
		}

		$data = $messages->all($domain);
		if (isset($options['as_tree']) && $options['as_tree']) {
			$data = \Symfony\Component\Translation\Util\ArrayConverter::expandToTree($data);
		}

		if (isset($options['inline']) && (0 < ($inline = (int) $options['inline']))) {
			return \Symfony\Component\Yaml\Yaml::dump($data, $inline);
		}

		return \Symfony\Component\Yaml\Yaml::dump($data);
	}

	protected function getExtension()
	{
		return 'yml';
	}
}

?>
