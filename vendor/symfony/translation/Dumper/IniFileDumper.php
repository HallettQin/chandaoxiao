<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

class IniFileDumper extends FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		$output = '';

		foreach ($messages->all($domain) as $source => $target) {
			$escapeTarget = str_replace('"', '\\"', $target);
			$output .= $source . '="' . $escapeTarget . "\"\n";
		}

		return $output;
	}

	protected function getExtension()
	{
		return 'ini';
	}
}

?>
