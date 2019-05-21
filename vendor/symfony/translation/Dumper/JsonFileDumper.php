<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

class JsonFileDumper extends FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		if (isset($options['json_encoding'])) {
			$flags = $options['json_encoding'];
		}
		else {
			$flags = (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
		}

		return json_encode($messages->all($domain), $flags);
	}

	protected function getExtension()
	{
		return 'json';
	}
}

?>
